<?php

namespace App\Modules\Nurture\Listeners;

use App\Modules\Lead\Events\LeadStatusChanged;
use App\Modules\Lead\Events\LeadTagged;
use App\Modules\Nurture\Services\NurtureService;

class HandleNurtureTriggers
{
    public function __construct(private readonly NurtureService $service) {}

    public function handleStatusChanged(LeadStatusChanged $event): void
    {
        $this->service->onStatusChanged($event->lead);
    }

    public function handleTagged(LeadTagged $event): void
    {
        $this->service->onTagged($event->lead, $event->tagIds);
    }
}
