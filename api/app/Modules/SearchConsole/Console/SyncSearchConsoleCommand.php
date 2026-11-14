<?php

namespace App\Modules\SearchConsole\Console;

use App\Modules\SearchConsole\Services\SearchConsoleSyncService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\TenantRunner;
use Illuminate\Console\Command;
use Throwable;

class SyncSearchConsoleCommand extends Command
{
    protected $signature = 'search-console:sync {--days=90}';

    protected $description = 'Importa as métricas de busca orgânica por post (Search Console)';

    public function handle(TenantRunner $runner, SearchConsoleSyncService $service): int
    {
        $runner->each(function (Tenant $tenant) use ($service) {
            $count = $service->sync($tenant->id, (int) $this->option('days'));
            if ($count) $this->line("Tenant {$tenant->id}: {$count} linha(s) post/dia.");
        }, fn (Tenant $tenant, Throwable $e) => $this->error("Tenant {$tenant->id}: {$e->getMessage()}"));

        return self::SUCCESS;
    }
}
