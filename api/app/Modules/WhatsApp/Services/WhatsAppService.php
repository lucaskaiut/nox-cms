<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Crm\Models\LeadActivity;
use App\Modules\Crm\Services\PipelineService;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Support\LeadRef;
use App\Modules\User\Models\User;
use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Support\PhoneNumber;
use App\Modules\WhatsApp\Support\TemplateDefinition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Conversas do WhatsApp do tenant atual: envio pelo painel, registro das
 * mensagens recebidas (webhook) e vínculo da conversa com o lead do CRM.
 */
class WhatsAppService
{
    public function __construct(private readonly PipelineService $pipeline) {}

    public function account(): WhatsAppAccount
    {
        $account = WhatsAppAccount::query()->first();
        if (! $account?->isConfigured() || ! $account->is_active) {
            throw new WhatsAppApiException('O WhatsApp ainda não está configurado. Acesse Configurações > WhatsApp.');
        }

        return $account;
    }

    public function client(?WhatsAppAccount $account = null): WhatsAppClient
    {
        return new WhatsAppClient($account ?? $this->account());
    }

    // ---------------------------------------------------------------- conversas

    public function conversationFor(string $waId, ?string $name = null, ?int $tenantId = null): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::query()->whereIn('wa_id', PhoneNumber::candidates($waId))->first()
            ?? WhatsAppConversation::query()->create(array_filter([
                'tenant_id' => $tenantId, 'wa_id' => $waId, 'contact_name' => $name,
            ], fn ($v) => $v !== null));

        if ($name && $conversation->contact_name !== $name) {
            $conversation->update(['contact_name' => $name]);
        }

        return $conversation;
    }

    /** Abre (ou reaproveita) a conversa com o telefone do lead. */
    public function startForLead(Lead $lead): WhatsAppConversation
    {
        $waId = PhoneNumber::toWaId($lead->phone);
        if ($waId === null) {
            throw new WhatsAppApiException('Este lead não tem um telefone válido para WhatsApp.');
        }

        $conversation = $this->conversationFor($waId, $lead->name);
        if ($conversation->lead_id === null) {
            $conversation->lead()->associate($lead)->save();
        }

        return $conversation;
    }

    /** Cria um lead no funil a partir de uma conversa que chegou sem código. */
    public function createLeadFromConversation(WhatsAppConversation $conversation, ?User $user): Lead
    {
        if ($conversation->lead) {
            return $conversation->lead;
        }

        $first = $conversation->messages()->where('direction', WhatsAppMessage::IN)->oldest('id')->first();
        $lead = $this->pipeline->createLead([
            'name' => $conversation->contact_name ?: PhoneNumber::format($conversation->wa_id),
            'phone' => PhoneNumber::format($conversation->wa_id),
            'message' => $first?->body,
        ], $user, 'whatsapp');
        $conversation->lead()->associate($lead)->save();

        return $lead;
    }

    public function markRead(WhatsAppConversation $conversation): void
    {
        if ($conversation->unread_count === 0) {
            return;
        }
        $conversation->update(['unread_count' => 0]);

        $last = $conversation->messages()->where('direction', WhatsAppMessage::IN)->whereNotNull('wamid')->latest('id')->first();
        if ($last) {
            try {
                $this->client()->markRead($last->wamid);
            } catch (Throwable) {
                // Confirmação de leitura é cortesia: falha não impede o atendimento.
            }
        }
    }

    // ------------------------------------------------------------------- envio

    public function sendText(WhatsAppConversation $conversation, string $body, ?User $user): WhatsAppMessage
    {
        if (! $conversation->windowOpen()) {
            throw new WhatsAppApiException('Fora da janela de 24 horas: o contato não escreveu nas últimas 24h. Envie um template aprovado para retomar a conversa.');
        }

        $response = $this->client()->sendText($conversation->wa_id, $body);

        return $this->storeOutgoing($conversation, $response, [
            'type' => 'text', 'body' => $body, 'user_id' => $user?->id,
        ]);
    }

    /**
     * @param  array<string, string>  $header
     * @param  array<string, string>  $body
     */
    public function sendTemplate(WhatsAppConversation $conversation, string $name, string $language, array $header, array $body, ?User $user): WhatsAppMessage
    {
        $template = $this->templates()->first(fn (TemplateDefinition $t) => $t->name === $name && $t->language === $language);
        if (! $template) {
            throw new WhatsAppApiException('Template não encontrado ou não aprovado. Atualize a lista e tente de novo.');
        }
        if (! $template->supported) {
            throw new WhatsAppApiException('Templates com imagem, vídeo ou documento no cabeçalho ainda não podem ser enviados pelo painel.');
        }
        $missing = array_filter([...$template->headerParams, ...$template->bodyParams],
            fn ($p) => blank(in_array($p, $template->headerParams, true) ? ($header[$p] ?? null) : ($body[$p] ?? null)));
        if ($missing !== []) {
            throw new WhatsAppApiException('Preencha todas as variáveis do template: '.implode(', ', $missing).'.');
        }

        $response = $this->client()->sendTemplate($conversation->wa_id, $name, $language, $template->components($header, $body));

        return $this->storeOutgoing($conversation, $response, [
            'type' => 'template', 'template_name' => $name, 'body' => $template->render($header, $body), 'user_id' => $user?->id,
        ]);
    }

    /** @return Collection<int, TemplateDefinition> */
    public function templates(bool $fresh = false): Collection
    {
        $account = $this->account();
        $key = "whatsapp:templates:{$account->tenant_id}";
        if ($fresh) {
            Cache::forget($key);
        }

        $raw = Cache::remember($key, now()->addMinutes(10), fn () => $this->client($account)->templates());

        return collect($raw)->map(fn (array $t) => TemplateDefinition::fromGraph($t))->sortBy('name')->values();
    }

    private function storeOutgoing(WhatsAppConversation $conversation, array $response, array $attributes): WhatsAppMessage
    {
        // A Meta devolve o wa_id canônico (ex.: celular sem o nono dígito):
        // usar esse valor faz as respostas caírem nesta mesma conversa.
        $canonical = $response['contacts'][0]['wa_id'] ?? null;
        if ($canonical && $canonical !== $conversation->wa_id
            && WhatsAppConversation::query()->where('wa_id', $canonical)->doesntExist()) {
            $conversation->wa_id = $canonical;
        }

        $message = $conversation->messages()->create($attributes + [
            'tenant_id' => $conversation->tenant_id,
            'wamid' => $response['messages'][0]['id'] ?? null,
            'direction' => WhatsAppMessage::OUT,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $conversation->fill([
            'last_message_preview' => Str::limit((string) $message->body, 250),
            'last_message_at' => now(),
        ])->save();

        if ($conversation->lead) {
            $conversation->lead->forceFill(['last_activity_at' => now()])->saveQuietly();
        }

        return $message;
    }

    // --------------------------------------------------------------- recebimento

    /** Processa uma mudança "messages" do webhook já no contexto do tenant da conta. */
    public function handleWebhookValue(WhatsAppAccount $account, array $value): void
    {
        $account->forceFill(['last_webhook_at' => now()])->saveQuietly();

        $names = collect($value['contacts'] ?? [])->mapWithKeys(
            fn ($c) => [($c['wa_id'] ?? '') => $c['profile']['name'] ?? null],
        );

        foreach ($value['messages'] ?? [] as $incoming) {
            $this->storeIncoming($account, $incoming, $names[$incoming['from'] ?? ''] ?? null);
        }

        foreach ($value['statuses'] ?? [] as $status) {
            $this->updateStatus($status);
        }
    }

    private function storeIncoming(WhatsAppAccount $account, array $incoming, ?string $name): void
    {
        $wamid = $incoming['id'] ?? null;
        if (! $wamid || ! isset($incoming['from'])
            || WhatsAppMessage::query()->where('wamid', $wamid)->exists()) {
            return; // reentrega da Meta
        }

        $conversation = $this->conversationFor($incoming['from'], $name, $account->tenant_id);
        $sentAt = isset($incoming['timestamp']) ? Carbon::createFromTimestamp((int) $incoming['timestamp']) : now();
        [$type, $body, $media] = $this->parseContent($incoming);

        $conversation->messages()->create([
            'tenant_id' => $account->tenant_id,
            'wamid' => $wamid,
            'direction' => WhatsAppMessage::IN,
            'type' => $type,
            'body' => $body,
            'media_id' => $media['id'] ?? null,
            'media_mime' => $media['mime_type'] ?? null,
            'media_filename' => $media['filename'] ?? null,
            'status' => 'received',
            'sent_at' => $sentAt,
        ]);

        $conversation->fill([
            'last_message_preview' => Str::limit($body ?? $this->typeLabel($type), 250),
            'last_message_at' => $sentAt,
            'last_inbound_at' => $sentAt,
            'unread_count' => $conversation->unread_count + 1,
        ])->save();

        $this->linkLead($conversation, $body);
    }

    /**
     * Liga a conversa ao lead: pelo código "NX-…" que o site coloca na
     * mensagem do WhatsApp ou, sem código, pelo telefone de um lead do
     * formulário. Confirma no histórico que o contato realmente escreveu.
     */
    private function linkLead(WhatsAppConversation $conversation, ?string $body): void
    {
        $lead = $conversation->lead;
        $ref = LeadRef::find($body);

        if (! $lead && $ref) {
            $lead = Lead::query()->where('ref', $ref)->first();
        }
        if (! $lead) {
            $candidates = PhoneNumber::candidates($conversation->wa_id);
            $lead = Lead::query()->whereNotNull('phone')->latest('id')->limit(500)->get()
                ->first(fn (Lead $l) => in_array(PhoneNumber::toWaId($l->phone), $candidates, true));
        }
        if (! $lead) {
            return;
        }

        if ($conversation->lead_id !== $lead->id) {
            $conversation->lead()->associate($lead)->save();
            $lead->fill([
                'name' => $lead->name ?: $conversation->contact_name,
                'phone' => $lead->phone ?: PhoneNumber::format($conversation->wa_id),
            ])->save();
            $this->pipeline->log($lead, LeadActivity::WHATSAPP_LINKED,
                'Contato escreveu no WhatsApp'.($ref ? " (código {$ref})" : '').'.',
                ['wa_id' => $conversation->wa_id, 'ref' => $ref]);
        } else {
            $lead->forceFill(['last_activity_at' => now()])->saveQuietly();
        }
    }

    private function updateStatus(array $status): void
    {
        $message = WhatsAppMessage::query()->where('wamid', $status['id'] ?? '')->first();
        $new = $status['status'] ?? null;
        if (! $message || ! $new) {
            return;
        }

        if ($new === 'failed') {
            $error = $status['errors'][0] ?? [];
            $message->update([
                'status' => 'failed',
                'error' => $error['error_data']['details'] ?? $error['message'] ?? $error['title'] ?? 'Falha na entrega.',
            ]);

            return;
        }

        $rank = WhatsAppMessage::STATUS_RANK;
        if (isset($rank[$new]) && ($rank[$new] > ($rank[$message->status] ?? -1))) {
            $message->update(['status' => $new]);
        }
    }

    /** @return array{0: string, 1: ?string, 2: ?array} tipo, texto, mídia */
    private function parseContent(array $m): array
    {
        $type = $m['type'] ?? 'unsupported';

        return match ($type) {
            'text' => ['text', $m['text']['body'] ?? '', null],
            'image', 'video', 'audio', 'document', 'sticker' => [$type, $m[$type]['caption'] ?? ($type === 'document' ? ($m['document']['filename'] ?? null) : null), $m[$type] ?? null],
            'button' => ['text', $m['button']['text'] ?? '', null],
            'interactive' => ['text', $m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? '', null],
            'reaction' => ['reaction', $m['reaction']['emoji'] ?? '', null],
            'location' => ['location', trim(($m['location']['name'] ?? '').' '.($m['location']['address'] ?? '').' https://maps.google.com/?q='.($m['location']['latitude'] ?? '').','.($m['location']['longitude'] ?? '')), null],
            'contacts' => ['contacts', collect($m['contacts'] ?? [])->map(fn ($c) => trim(($c['name']['formatted_name'] ?? '').' '.($c['phones'][0]['phone'] ?? '')))->implode("\n"), null],
            default => ['unsupported', 'Mensagem de um tipo que o painel ainda não exibe. Veja no celular.', null],
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'image' => '📷 Imagem', 'video' => '🎬 Vídeo', 'audio' => '🎤 Áudio',
            'document' => '📄 Documento', 'sticker' => 'Figurinha', default => 'Mensagem',
        };
    }
}
