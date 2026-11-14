<?php

namespace App\Modules\GoogleAds\Services;

use App\Modules\GoogleAds\Models\GoogleAdsAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Chamadas REST à Google Ads API com OAuth (refresh token). */
class GoogleAdsClient
{
    public function __construct(private readonly GoogleAdsAccount $account) {}

    /**
     * @param  list<array<string, mixed>>  $conversions
     * @return array{results: list<array>, errors: array<int, array{code: string, message: string}>}
     */
    public function uploadClickConversions(array $conversions): array
    {
        $response = $this->request()->post($this->url(':uploadClickConversions'), [
            'conversions' => $conversions,
            'partialFailure' => true,
        ]);
        $this->throwIfFailed($response);

        return [
            'results' => $response->json('results', []),
            'errors' => $this->partialFailures($response->json('partialFailureError')),
        ];
    }

    /** Ações de conversão do tipo "importação de cliques" da conta. */
    public function conversionActions(): array
    {
        $response = $this->request()->post($this->url('/googleAds:search'), [
            'query' => "SELECT conversion_action.id, conversion_action.name, conversion_action.status, conversion_action.type FROM conversion_action WHERE conversion_action.type = 'UPLOAD_CLICKS' AND conversion_action.status != 'REMOVED'",
        ]);
        $this->throwIfFailed($response);

        return collect($response->json('results', []))->map(fn ($row) => [
            'id' => (string) data_get($row, 'conversionAction.id'),
            'name' => data_get($row, 'conversionAction.name'),
            'status' => data_get($row, 'conversionAction.status'),
        ])->values()->all();
    }

    public function conversionActionResource(string $id): string
    {
        return "customers/{$this->customerId()}/conversionActions/{$id}";
    }

    private function request(): PendingRequest
    {
        $headers = ['developer-token' => (string) $this->account->developer_token];
        if (filled($this->account->login_customer_id)) {
            $headers['login-customer-id'] = self::digits($this->account->login_customer_id);
        }

        return Http::withToken($this->accessToken())->withHeaders($headers)->acceptJson()->timeout(60)->connectTimeout(10);
    }

    private function accessToken(): string
    {
        $key = 'google-ads-token:'.$this->account->tenant_id.':'.md5((string) $this->account->client_id.$this->account->refresh_token);

        return Cache::remember($key, now()->addMinutes(50), function () {
            $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $this->account->client_id,
                'client_secret' => $this->account->client_secret,
                'refresh_token' => $this->account->refresh_token,
            ]);

            if ($response->failed() || blank($response->json('access_token'))) {
                throw new RuntimeException('Não foi possível autenticar no Google (OAuth): '.($response->json('error_description') ?? $response->json('error') ?? 'resposta inválida').'. Confira client ID, client secret e refresh token.');
            }

            return (string) $response->json('access_token');
        });
    }

    private function url(string $suffix): string
    {
        $base = rtrim((string) config('leads.google_ads_api.base_url'), '/');
        $version = config('leads.google_ads_api.version');

        return "{$base}/{$version}/customers/{$this->customerId()}{$suffix}";
    }

    private function customerId(): string
    {
        return self::digits((string) $this->account->customer_id);
    }

    public static function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }

    private function throwIfFailed(\Illuminate\Http\Client\Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $error = $response->json('error') ?? $response->json('0.error');
        $details = collect(data_get($error, 'details', []))->flatMap(fn ($d) => $d['errors'] ?? [])
            ->map(fn ($e) => $e['message'] ?? null)->filter()->implode(' ');
        $message = trim(($error['message'] ?? 'Erro HTTP '.$response->status()).' '.$details);

        throw new RuntimeException('Google Ads API: '.$message, $response->status(), new RequestException($response));
    }

    /** Erros parciais indexados pela posição da conversão na requisição. */
    private function partialFailures(?array $error): array
    {
        if (! $error) {
            return [];
        }

        $byIndex = [];
        foreach ($error['details'] ?? [] as $detail) {
            foreach ($detail['errors'] ?? [] as $item) {
                $index = null;
                foreach (data_get($item, 'location.fieldPathElements', []) as $element) {
                    if (($element['fieldName'] ?? null) === 'conversions' && isset($element['index'])) {
                        $index = (int) $element['index'];
                        break;
                    }
                }
                if ($index === null) continue;

                $code = collect($item['errorCode'] ?? [])->map(fn ($v, $k) => is_string($v) ? $v : $k)->first() ?? 'UNKNOWN';
                $byIndex[$index] = ['code' => (string) $code, 'message' => (string) ($item['message'] ?? $code)];
            }
        }

        return $byIndex;
    }
}
