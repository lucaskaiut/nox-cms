<?php

namespace App\Modules\GoogleAds\Console;

use App\Modules\GoogleAds\Services\ConversionUploadService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\TenantRunner;
use Illuminate\Console\Command;
use Throwable;

class UploadPendingConversions extends Command
{
    protected $signature = 'google-ads:upload-conversions';

    protected $description = 'Envia ao Google Ads as conversões offline pendentes (com nova tentativa)';

    public function handle(TenantRunner $runner, ConversionUploadService $service): int
    {
        $runner->each(function (Tenant $tenant) use ($service) {
            [$ok, $fail] = $service->processPending($tenant->id);
            if ($ok || $fail) {
                $this->line("Tenant {$tenant->id}: {$ok} enviada(s), {$fail} com erro.");
            }
        }, fn (Tenant $tenant, Throwable $e) => $this->error("Tenant {$tenant->id}: {$e->getMessage()}"));

        return self::SUCCESS;
    }
}
