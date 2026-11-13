<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Chamadas à Graph API da WhatsApp Cloud API para uma conta configurada.
 * https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class WhatsAppClient
{
    public const BASE_URL = 'https://graph.facebook.com';

    public function __construct(private readonly WhatsAppAccount $account) {}

    /** Dados do número (valida token + phone_number_id). */
    public function phoneNumber(): array
    {
        return $this->get($this->account->phone_number_id, [
            'fields' => 'display_phone_number,verified_name,quality_rating,code_verification_status',
        ]);
    }

    /**
     * Inscreve o app do token nos webhooks da WABA. Sem Embedded Signup este
     * passo não acontece sozinho: sem ele a Meta não envia as mensagens.
     */
    public function subscribeApp(): array
    {
        return $this->post("{$this->account->waba_id}/subscribed_apps", []);
    }

    public function sendText(string $to, string $body): array
    {
        return $this->post("{$this->account->phone_number_id}/messages", [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body, 'preview_url' => true],
        ]);
    }

    /** @param list<array> $components */
    public function sendTemplate(string $to, string $name, string $language, array $components): array
    {
        $template = ['name' => $name, 'language' => ['code' => $language]];
        if ($components !== []) {
            $template['components'] = $components;
        }

        return $this->post("{$this->account->phone_number_id}/messages", [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => $template,
        ]);
    }

    /** Marca a mensagem recebida como lida (tiques azuis para o contato). */
    public function markRead(string $wamid): void
    {
        $this->post("{$this->account->phone_number_id}/messages", [
            'messaging_product' => 'whatsapp', 'status' => 'read', 'message_id' => $wamid,
        ]);
    }

    /** @return list<array> templates aprovados da WABA */
    public function templates(): array
    {
        $body = $this->get("{$this->account->waba_id}/message_templates", [
            'fields' => 'name,language,status,category,components',
            'status' => 'APPROVED',
            'limit' => 200,
        ]);

        return $body['data'] ?? [];
    }

    /** @return array{content: string, mime: string} */
    public function downloadMedia(string $mediaId): array
    {
        $meta = $this->get($mediaId);
        $response = $this->send(fn (PendingRequest $http) => $http->get($meta['url'] ?? ''), json: false);

        return ['content' => $response->body(), 'mime' => $meta['mime_type'] ?? $response->header('Content-Type')];
    }

    private function get(string $path, array $query = []): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get($this->url($path), $query))->json() ?? [];
    }

    private function post(string $path, array $payload): array
    {
        return $this->send(fn (PendingRequest $http) => $http->asJson()->post($this->url($path), $payload))->json() ?? [];
    }

    private function url(string $path): string
    {
        return sprintf('%s/%s/%s', self::BASE_URL, $this->account->graph_version ?: 'v23.0', ltrim($path, '/'));
    }

    private function send(callable $request, bool $json = true): Response
    {
        if (blank($this->account->access_token)) {
            throw new WhatsAppApiException('Configure o token de acesso do WhatsApp antes de usar o chat.');
        }

        try {
            $http = Http::withToken($this->account->access_token)->timeout(20);
            $response = $request($json ? $http->acceptJson() : $http);
        } catch (ConnectionException) {
            throw new WhatsAppApiException('Não foi possível falar com a API do WhatsApp. Tente de novo em instantes.', null, 502);
        }

        if ($response->failed()) {
            throw WhatsAppApiException::fromResponse($response->json() ?? [], $response->status());
        }

        return $response;
    }
}
