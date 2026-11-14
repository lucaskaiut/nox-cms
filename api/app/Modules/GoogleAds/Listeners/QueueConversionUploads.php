<?php

namespace App\Modules\GoogleAds\Listeners;

use App\Modules\GoogleAds\Jobs\UploadConversions;
use App\Modules\GoogleAds\Services\ConversionUploadService;
use App\Modules\Lead\Events\LeadStatusChanged;

class QueueConversionUploads
{
    public function __construct(private readonly ConversionUploadService $service) {}

    public function handle(LeadStatusChanged $event): void
    {
        if ($this->service->queue($event->lead) > 0) {
            // Pequeno atraso para agrupar mudanças em sequência (qualificado → ganho).
            UploadConversions::dispatch($event->lead->tenant_id)->delay(now()->addMinute())->afterCommit();
        }
    }
}
