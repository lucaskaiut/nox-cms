<?php

namespace App\Modules\Nurture\Console;

use App\Modules\Nurture\Services\NurtureService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\TenantRunner;
use Illuminate\Console\Command;
use Throwable;

class ProcessNurture extends Command
{
    protected $signature = 'nurture:process {--enroll-lost : também inscreve perdidos nas sequências de reativação}';

    protected $description = 'Envia os e-mails de nutrição vencidos';

    public function handle(TenantRunner $runner, NurtureService $service): int
    {
        $runner->each(function (Tenant $tenant) use ($service) {
            $enrolled = $this->option('enroll-lost') ? $service->enrollLost() : 0;
            $sent = $service->processDue();
            if ($enrolled || $sent) $this->line("Tenant {$tenant->id}: {$enrolled} inscrito(s), {$sent} e-mail(s).");
        }, fn (Tenant $tenant, Throwable $e) => $this->error("Tenant {$tenant->id}: {$e->getMessage()}"));

        return self::SUCCESS;
    }
}
