<?php

namespace App\Modules\Crm\Listeners;

use App\Modules\Crm\Services\LeadNotifier;
use App\Modules\Lead\Events\LeadCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyNewLead implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private readonly LeadNotifier $notifier) {}

    public function handle(LeadCreated $event): void
    {
        // Cadastro manual: quem cadastrou já sabe do lead.
        if ($event->lead->channel !== 'manual') {
            $this->notifier->newLead($event->lead);
        }
    }
}
