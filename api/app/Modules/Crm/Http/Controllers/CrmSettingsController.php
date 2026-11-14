<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Modules\Analytics\Services\AttributionService;
use App\Modules\Crm\Models\CrmSettings;
use App\Modules\Crm\Services\LeadNotifier;
use App\Modules\Crm\Support\LeadTemplate;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Site, motivos de perda, alertas e resposta automática. */
class CrmSettingsController extends ApiController
{
    public function show(): JsonResponse
    {
        return $this->success($this->present(CrmSettings::current()));
    }

    /** Leitura liberada para quem vê leads (motivos de perda no quadro). */
    public function lostReasons(): JsonResponse
    {
        return $this->success(CrmSettings::current()->lostReasons());
    }

    public function update(Request $request, AttributionService $attribution): JsonResponse
    {
        $data = $request->validate([
            'site_url' => ['nullable', 'url:http,https', 'max:255'],
            'post_url_pattern' => ['nullable', 'string', 'max:255', 'regex:/^\/[\w\-\/]*\{slug\}[\w\-\/]*$/'],
            'lost_reasons' => ['nullable', 'array', 'max:30'], 'lost_reasons.*' => ['string', 'max:120'],
            'stale_lead_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
            'stale_post_months' => ['sometimes', 'integer', 'min:1', 'max:36'],
            'notify_emails' => ['nullable', 'array', 'max:10'], 'notify_emails.*' => ['email:rfc', 'max:160'],
            'notify_new_lead' => ['sometimes', 'boolean'],
            'notify_daily_digest' => ['sometimes', 'boolean'],
            'telegram_bot_token' => ['nullable', 'string', 'max:120', 'regex:/^\d+:[\w-]+$/'],
            'remove_telegram_bot_token' => ['sometimes', 'boolean'],
            'telegram_chat_id' => ['nullable', 'string', 'max:60', 'regex:/^-?\d+$|^@\w+$/'],
            'auto_reply_enabled' => ['sometimes', 'boolean'],
            'auto_reply_subject' => ['nullable', 'string', 'max:160'],
            'auto_reply_body' => ['nullable', 'string', 'max:5000', 'required_if_accepted:auto_reply_enabled'],
            'email_signature' => ['nullable', 'string', 'max:500'],
        ], ['post_url_pattern.regex' => 'Use um caminho com {slug}, por exemplo /blog/{slug}.']);

        $settings = CrmSettings::current();
        $settings->tenant_id ??= TenantContext::tenantId();
        if (array_key_exists('lost_reasons', $data)) {
            $data['lost_reasons'] = array_values(array_unique(array_filter(array_map('trim', $data['lost_reasons'] ?? []))));
        }
        if (array_key_exists('notify_emails', $data)) {
            $data['notify_emails'] = array_values(array_unique(array_map('strtolower', $data['notify_emails'] ?? [])));
        }
        if ($request->boolean('remove_telegram_bot_token')) {
            $data['telegram_bot_token'] = null;
        } elseif (blank($data['telegram_bot_token'] ?? null)) {
            unset($data['telegram_bot_token']); // em branco = manter o salvo
        }
        unset($data['remove_telegram_bot_token']);

        $settings->fill($data)->save();

        if ($settings->wasChanged(['site_url', 'post_url_pattern']) || $settings->wasRecentlyCreated) {
            $attribution->backfill();
        }

        return $this->success($this->present($settings), 'Configurações salvas.');
    }

    public function test(LeadNotifier $notifier): JsonResponse
    {
        try {
            $channels = $notifier->test(CrmSettings::current());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['telegram_bot_token' => $e->getMessage()]);
        }

        if ($channels === []) {
            throw ValidationException::withMessages(['notify_emails' => 'Nenhum canal configurado para receber alertas.']);
        }

        return $this->success(['channels' => $channels], 'Alerta de teste enviado.');
    }

    private function present(CrmSettings $s): array
    {
        return [
            'site_url' => $s->site_url,
            'post_url_pattern' => $s->post_url_pattern,
            'lost_reasons' => $s->lostReasons(),
            'stale_lead_days' => $s->stale_lead_days,
            'stale_post_months' => $s->stale_post_months,
            'notify_emails' => $s->notify_emails ?? [],
            'notify_new_lead' => $s->notify_new_lead,
            'notify_daily_digest' => $s->notify_daily_digest,
            'has_telegram_bot_token' => filled($s->telegram_bot_token),
            'telegram_chat_id' => $s->telegram_chat_id,
            'auto_reply_enabled' => $s->auto_reply_enabled,
            'auto_reply_subject' => $s->auto_reply_subject,
            'auto_reply_body' => $s->auto_reply_body,
            'email_signature' => $s->email_signature,
            'template_variables' => LeadTemplate::VARIABLES,
        ];
    }
}
