<?php

namespace App\Modules\GoogleAds\Jobs;

use App\Modules\GoogleAds\Services\ConversionUploadService;
use App\Modules\Tenant\Support\TenantRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UploadConversions implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public function __construct(public int $tenantId) {}

    public function uniqueId(): string
    {
        return (string) $this->tenantId;
    }

    public function handle(ConversionUploadService $service, TenantRunner $runner): void
    {
        $runner->run($this->tenantId, fn () => $service->processPending($this->tenantId));
    }
}
