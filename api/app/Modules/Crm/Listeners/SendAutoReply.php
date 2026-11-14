<?php

namespace App\Modules\Crm\Listeners;

use App\Modules\Crm\Models\CrmSettings;
use App\Modules\Crm\Services\LeadMailer;
use App\Modules\Lead\Events\LeadCreated;
use App\Modules\Tenant\Support\TenantRunner;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Confirma o recebimento do formulário por e-mail, se ativado. */
class SendAutoReply implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private readonly LeadMailer $mailer, private readonly TenantRunner $runner) {}

    public function handle(LeadCreated $event): void
    {
        $this->runner->run($event->lead->tenant_id, fn () => $this->reply($event->lead));
    }

    private function reply(\App\Modules\Lead\Models\Lead $lead): void
    {
        $settings = CrmSettings::current($lead->tenant_id);

        if ($lead->channel !== 'form' || ! $settings->auto_reply_enabled || blank($settings->auto_reply_body)) {
            return;
        }

        $this->mailer->send(
            $lead,
            $settings->auto_reply_subject ?: 'Recebemos sua mensagem',
            $settings->auto_reply_body,
            null,
            ['kind' => 'auto_reply'],
        );
    }
}
