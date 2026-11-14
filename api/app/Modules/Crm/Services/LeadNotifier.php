<?php

namespace App\Modules\Crm\Services;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Crm\Mail\DailyDigestMail;
use App\Modules\Crm\Mail\NewLeadMail;
use App\Modules\Crm\Models\CrmSettings;
use App\Modules\Lead\Models\Lead;
use App\Modules\User\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Alertas comerciais por e-mail e Telegram. Destinatários: os e-mails das
 * configurações ou, se vazio, todos os usuários do tenant que veem leads.
 */
class LeadNotifier
{
    public function newLead(Lead $lead): void
    {
        $settings = CrmSettings::current($lead->tenant_id);
        if (! $settings->notify_new_lead) {
            return;
        }

        $lead->loadMissing('post');
        $this->mail($settings, $lead->tenant_id, new NewLeadMail($lead, $this->leadUrl($lead)));

        $lines = array_filter([
            '🔔 <b>Novo lead</b> '.e($lead->ref),
            $lead->name ? e($lead->name).($lead->company ? ' · '.e($lead->company) : '') : null,
            $lead->phone ? '📞 '.e($lead->phone) : null,
            $lead->email ? '✉️ '.e($lead->email) : null,
            $lead->post ? '📝 Post: '.e($lead->post->title) : null,
            $lead->cta ? '🎯 Oferta: '.e($lead->cta) : null,
            $lead->message ? '💬 '.e(mb_strimwidth($lead->message, 0, 300, '…')) : null,
            $this->leadUrl($lead),
        ]);
        $this->telegram($settings, implode("\n", $lines));
    }

    /** @param array{new_leads: int, overdue: \Illuminate\Support\Collection, today: \Illuminate\Support\Collection, stale: \Illuminate\Support\Collection} $digest */
    public function dailyDigest(int $tenantId, array $digest): void
    {
        $settings = CrmSettings::current($tenantId);
        if (! $settings->notify_daily_digest) {
            return;
        }
        if ($digest['new_leads'] === 0 && $digest['overdue']->isEmpty() && $digest['today']->isEmpty() && $digest['stale']->isEmpty()) {
            return;
        }

        $this->mail($settings, $tenantId, new DailyDigestMail($digest, $this->panelUrl('/tarefas')));
        $this->telegram($settings, sprintf(
            "☀️ <b>Resumo do dia</b>\n%d lead(s) novo(s) ontem\n%d tarefa(s) atrasada(s), %d para hoje\n%d lead(s) parado(s)\n%s",
            $digest['new_leads'], $digest['overdue']->count(), $digest['today']->count(), $digest['stale']->count(), $this->panelUrl('/tarefas'),
        ));
    }

    /** Envia uma mensagem de teste pelos canais configurados; retorna os canais usados. */
    public function test(CrmSettings $settings): array
    {
        $channels = [];
        $recipients = $this->recipients($settings, $settings->tenant_id);
        if ($recipients !== []) {
            Mail::to($recipients)->send(new \App\Modules\Crm\Mail\SimpleMail('Teste de alertas do CRM', 'Se você recebeu este e-mail, os alertas do CRM estão funcionando.'));
            $channels[] = 'email';
        }
        if (filled($settings->telegram_bot_token) && filled($settings->telegram_chat_id)) {
            $this->telegram($settings, '✅ Teste de alertas do CRM: funcionando.', throw: true);
            $channels[] = 'telegram';
        }

        return $channels;
    }

    public function telegram(CrmSettings $settings, string $html, bool $throw = false): void
    {
        if (blank($settings->telegram_bot_token) || blank($settings->telegram_chat_id)) {
            return;
        }

        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$settings->telegram_bot_token}/sendMessage", [
                'chat_id' => $settings->telegram_chat_id,
                'text' => $html,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ])->throw();
        } catch (Throwable $e) {
            Log::warning('Falha ao enviar alerta no Telegram', ['tenant' => $settings->tenant_id, 'error' => $e->getMessage()]);
            if ($throw) {
                throw new \RuntimeException('O Telegram recusou a mensagem. Confira o token do bot e o chat ID.', 0, $e);
            }
        }
    }

    public function mail(CrmSettings $settings, int $tenantId, Mailable $mail): void
    {
        $recipients = $this->recipients($settings, $tenantId);
        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send($mail);
        } catch (Throwable $e) {
            Log::warning('Falha ao enviar alerta por e-mail', ['tenant' => $tenantId, 'error' => $e->getMessage()]);
        }
    }

    /** @return list<string> */
    public function recipients(CrmSettings $settings, int $tenantId): array
    {
        $emails = array_values(array_filter($settings->notify_emails ?? [], fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
        if ($emails !== []) {
            return $emails;
        }

        return User::query()->forTenant($tenantId)->get()
            ->filter(fn (User $user) => $user->hasPermission(Permission::LEAD_READ))
            ->pluck('email')->filter()->values()->all();
    }

    public function leadUrl(Lead $lead): string
    {
        return $this->panelUrl('/crm?lead='.$lead->uuid);
    }

    public function panelUrl(string $path): string
    {
        return rtrim((string) config('leads.panel_url'), '/').$path;
    }
}
