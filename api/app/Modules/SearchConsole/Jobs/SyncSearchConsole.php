<?php

namespace App\Modules\SearchConsole\Jobs;

use App\Modules\SearchConsole\Services\SearchConsoleSyncService;
use App\Modules\Tenant\Support\TenantRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSearchConsole implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $tenantId) {}

    public function uniqueId(): string
    {
        return (string) $this->tenantId;
    }

    public function handle(SearchConsoleSyncService $service, TenantRunner $runner): void
    {
        $runner->run($this->tenantId, fn () => $service->sync($this->tenantId));
    }
}
