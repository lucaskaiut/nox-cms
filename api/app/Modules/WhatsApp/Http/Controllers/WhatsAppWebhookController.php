<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Support\CurrentTenant;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint público chamado pela Meta (Configuração do webhook no app):
 * GET para verificar o token, POST com mensagens e status de entrega.
 * O tenant vem do phone_number_id; a assinatura usa o app secret da conta.
 */
class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsAppService $service,
        private readonly CurrentTenant $tenant,
    ) {}

    public function verify(Request $request): Response
    {
        $token = (string) $request->query('hub_verify_token');
        $valid = $request->query('hub_mode') === 'subscribe' && $token !== ''
            && WhatsAppAccount::withoutTenancy()->where('verify_token', $token)->exists();

        return $valid
            ? response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain'])
            : response('Forbidden', 403);
    }

    public function receive(Request $request): Response
    {
        $raw = $request->getContent();
        $payload = json_decode($raw, true);
        if (! is_array($payload) || ($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response('ignored', 200);
        }

        $signature = (string) $request->header('X-Hub-Signature-256');
        $verified = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }
                $value = $change['value'] ?? [];
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
                $account = $phoneNumberId
                    ? WhatsAppAccount::withoutTenancy()->with('tenant')->where('phone_number_id', $phoneNumberId)->first()
                    : null;
                if (! $account) {
                    Log::info('[WhatsApp] webhook para número não configurado', ['phone_number_id' => $phoneNumberId]);

                    continue;
                }

                $verified[$account->id] ??= $this->validSignature($raw, $signature, $account);
                if (! $verified[$account->id]) {
                    Log::warning('[WhatsApp] assinatura inválida no webhook', ['tenant_id' => $account->tenant_id]);

                    return response('Invalid signature', 403);
                }

                $this->tenant->set($account->tenant);
                try {
                    $this->service->handleWebhookValue($account, $value);
                } finally {
                    $this->tenant->forget();
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    private function validSignature(string $raw, string $header, WhatsAppAccount $account): bool
    {
        if (blank($account->app_secret) || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $raw, $account->app_secret), $header);
    }
}
