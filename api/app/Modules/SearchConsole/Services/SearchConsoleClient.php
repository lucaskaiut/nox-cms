<?php

namespace App\Modules\SearchConsole\Services;

use App\Modules\SearchConsole\Models\SearchConsoleAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Search Console API autenticada por conta de serviço (JWT assinado com RS256). */
class SearchConsoleClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    private const BASE = 'https://www.googleapis.com/webmasters/v3';

    public function __construct(private readonly SearchConsoleAccount $account) {}

    /** @return list<array{siteUrl: string, permissionLevel: string}> */
    public function sites(): array
    {
        $response = Http::withToken($this->accessToken())->timeout(30)->get(self::BASE.'/sites');
        $this->throwIfFailed($response);

        return $response->json('siteEntry', []);
    }

    /**
     * Linhas por data e página no período (paginado internamente).
     *
     * @return list<array{date: string, page: string, clicks: int, impressions: int, position: float}>
     */
    public function pageMetrics(string $startDate, string $endDate): array
    {
        $rows = [];
        $startRow = 0;
        $url = self::BASE.'/sites/'.rawurlencode((string) $this->account->site_url).'/searchAnalytics/query';

        do {
            $response = Http::withToken($this->accessToken())->timeout(60)->post($url, [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['date', 'page'],
                'rowLimit' => 25000,
                'startRow' => $startRow,
            ]);
            $this->throwIfFailed($response);

            $batch = $response->json('rows', []);
            foreach ($batch as $row) {
                $rows[] = [
                    'date' => $row['keys'][0],
                    'page' => $row['keys'][1],
                    'clicks' => (int) $row['clicks'],
                    'impressions' => (int) $row['impressions'],
                    'position' => (float) $row['position'],
                ];
            }
            $startRow += count($batch);
        } while (count($batch) === 25000);

        return $rows;
    }

    /** @return array{client_email: string, private_key: string} */
    public static function parseCredentials(string $json): array
    {
        $data = json_decode($json, true);
        if (! is_array($data) || ($data['type'] ?? null) !== 'service_account' || blank($data['client_email'] ?? null) || blank($data['private_key'] ?? null)) {
            throw new RuntimeException('Cole o JSON da chave da conta de serviço (type "service_account").');
        }

        return ['client_email' => $data['client_email'], 'private_key' => $data['private_key']];
    }

    private function accessToken(): string
    {
        $credentials = self::parseCredentials((string) $this->account->service_account_json);
        $key = 'gsc-token:'.$this->account->tenant_id.':'.md5($credentials['client_email'].$credentials['private_key']);

        return Cache::remember($key, now()->addMinutes(50), function () use ($credentials) {
            $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->jwt($credentials),
            ]);

            if ($response->failed() || blank($response->json('access_token'))) {
                throw new RuntimeException('O Google recusou a conta de serviço: '.($response->json('error_description') ?? $response->json('error') ?? 'resposta inválida'));
            }

            return (string) $response->json('access_token');
        });
    }

    private function jwt(array $credentials): string
    {
        $now = time();
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        $input = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        $key = openssl_pkey_get_private($credentials['private_key']);
        if ($key === false || ! openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('A chave privada da conta de serviço é inválida.');
        }

        return $input.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    private function throwIfFailed(\Illuminate\Http\Client\Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('error.message') ?? 'HTTP '.$response->status();
        if ($response->status() === 403) {
            $message .= ' Adicione o e-mail da conta de serviço como usuário da propriedade no Search Console.';
        }

        throw new RuntimeException('Search Console: '.$message, $response->status());
    }
}
