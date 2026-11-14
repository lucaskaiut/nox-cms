<?php

namespace App\Modules\Tenant\Support;

use App\Modules\Tenant\Models\Tenant;
use Closure;
use Throwable;

/**
 * Executa uma rotina (comando agendado, job) dentro do contexto de cada tenant,
 * para que os escopos globais e o preenchimento de tenant_id funcionem fora de
 * uma requisição HTTP. Restaura o contexto anterior ao final.
 */
final class TenantRunner
{
    public function __construct(private readonly CurrentTenant $context) {}

    public function run(Tenant|int $tenant, Closure $callback): mixed
    {
        $tenant = $tenant instanceof Tenant ? $tenant : Tenant::query()->findOrFail($tenant);
        $previous = $this->context->tenant();
        $this->context->set($tenant);

        try {
            return $callback($tenant);
        } finally {
            $previous ? $this->context->set($previous) : $this->context->forget();
        }
    }

    /**
     * @param  Closure(Tenant): void  $callback
     * @param  (Closure(Tenant, Throwable): void)|null  $onError  falha de um tenant não interrompe os demais
     */
    public function each(Closure $callback, ?Closure $onError = null): void
    {
        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($callback, $onError) {
            try {
                $this->run($tenant, $callback);
            } catch (Throwable $e) {
                if ($onError === null) {
                    throw $e;
                }
                $onError($tenant, $e);
            }
        });
    }
}
