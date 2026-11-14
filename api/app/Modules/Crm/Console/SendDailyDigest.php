<?php

namespace App\Modules\Crm\Console;

use App\Modules\Crm\Services\LeadNotifier;
use App\Modules\Crm\Services\TaskService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\TenantRunner;
use Illuminate\Console\Command;
use Throwable;

class SendDailyDigest extends Command
{
    protected $signature = 'crm:daily-digest';

    protected $description = 'Envia o resumo diário (leads novos, tarefas, leads parados)';

    public function handle(TenantRunner $runner, TaskService $tasks, LeadNotifier $notifier): int
    {
        $timezone = (string) config('leads.google_ads.timezone');
        $runner->each(
            fn (Tenant $tenant) => $notifier->dailyDigest($tenant->id, $tasks->digest($timezone)),
            fn (Tenant $tenant, Throwable $e) => $this->error("Tenant {$tenant->id}: {$e->getMessage()}"),
        );

        return self::SUCCESS;
    }
}
