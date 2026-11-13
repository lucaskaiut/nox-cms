<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Services\WhatsAppClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Configuração manual da WhatsApp Cloud API (sem Embedded Signup). */
class WhatsAppAccountController extends ApiController
{
    public function show(): JsonResponse
    {
        return $this->success($this->present($this->account()));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_number_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'waba_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'access_token' => ['nullable', 'string', 'max:2000'],
            'app_secret' => ['nullable', 'string', 'max:200'],
            'verify_token' => ['required', 'string', 'min:16', 'max:80', 'regex:/^[\w.~-]+$/'],
            'graph_version' => ['required', 'string', 'regex:/^v\d{2}\.\d$/'],
        ]);

        $account = $this->account();
        $changedNumber = $account->phone_number_id !== $data['phone_number_id'];
        foreach (['access_token', 'app_secret'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]); // em branco = manter o atual
            }
        }
        $account->fill($data);
        if ($changedNumber || $account->isDirty('access_token') || $account->isDirty('waba_id')) {
            // Credenciais novas precisam ser testadas de novo antes de ativar.
            $account->fill(['is_active' => false, 'verified_at' => null]);
        }
        $account->save();

        return $this->success($this->present($account), 'Configuração salva. Clique em "Testar e ativar".');
    }

    /** Valida token e número na Graph API, inscreve o app nos webhooks e ativa. */
    public function activate(): JsonResponse
    {
        $account = $this->account();
        if (! $account->isConfigured() || blank($account->app_secret)) {
            return $this->fail('Preencha Phone Number ID, WABA ID, token de acesso e app secret antes de ativar.');
        }

        $client = new WhatsAppClient($account);
        $number = $client->phoneNumber();
        $client->subscribeApp();

        $account->fill([
            'display_phone' => $number['display_phone_number'] ?? null,
            'verified_name' => $number['verified_name'] ?? null,
            'verified_at' => now(),
            'is_active' => true,
        ])->save();

        return $this->success($this->present($account) + ['quality_rating' => $number['quality_rating'] ?? null], 'WhatsApp conectado.');
    }

    public function deactivate(): JsonResponse
    {
        $account = $this->account();
        $account->update(['is_active' => false]);

        return $this->success($this->present($account), 'WhatsApp desativado no painel.');
    }

    private function account(): WhatsAppAccount
    {
        return WhatsAppAccount::query()->firstOrCreate([], ['verify_token' => Str::random(40), 'graph_version' => 'v23.0', 'is_active' => false]);
    }

    private function fail(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => []], 422);
    }

    /** A Meta só aceita HTTPS; atrás do proxy (Traefik) a requisição chega como http. */
    private function webhookUrl(): string
    {
        $url = url('/api/whatsapp/webhook');

        return app()->environment('local', 'testing') ? $url : preg_replace('/^http:/', 'https:', $url);
    }

    private function present(WhatsAppAccount $account): array
    {
        return [
            'phone_number_id' => $account->phone_number_id,
            'waba_id' => $account->waba_id,
            'display_phone' => $account->display_phone,
            'verified_name' => $account->verified_name,
            'graph_version' => $account->graph_version,
            'verify_token' => $account->verify_token,
            'webhook_url' => $this->webhookUrl(),
            'has_access_token' => filled($account->access_token),
            'has_app_secret' => filled($account->app_secret),
            'is_active' => $account->is_active,
            'verified_at' => $account->verified_at?->toIso8601String(),
            'last_webhook_at' => $account->last_webhook_at?->toIso8601String(),
        ];
    }
}
