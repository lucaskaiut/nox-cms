<?php

namespace App\Modules\SearchConsole\Http\Controllers;

use App\Modules\SearchConsole\Jobs\SyncSearchConsole;
use App\Modules\SearchConsole\Models\SearchConsoleAccount;
use App\Modules\SearchConsole\Services\SearchConsoleClient;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SearchConsoleController extends ApiController
{
    public function show(): JsonResponse
    {
        return $this->success($this->present($this->account()));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_url' => ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/.+\/|sc-domain:[\w.-]+)$/'],
            'service_account_json' => ['nullable', 'string', 'max:10000'],
        ], ['site_url.regex' => 'Use a URL da propriedade terminando em "/" (https://site.com/) ou sc-domain:site.com.']);

        $account = $this->account();
        if (filled($data['service_account_json'] ?? null)) {
            try {
                $credentials = SearchConsoleClient::parseCredentials($data['service_account_json']);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['service_account_json' => $e->getMessage()]);
            }
            $account->service_account_email = $credentials['client_email'];
            $account->service_account_json = $data['service_account_json'];
        }
        if (array_key_exists('site_url', $data)) {
            $account->site_url = $data['site_url'];
        }
        $account->save();

        return $this->success($this->present($account), 'Configuração salva.');
    }

    public function activate(): JsonResponse
    {
        $account = $this->account();
        if (! $account->isConfigured()) {
            throw ValidationException::withMessages(['site_url' => 'Informe a propriedade e a chave da conta de serviço.']);
        }

        try {
            $sites = collect((new SearchConsoleClient($account))->sites())->pluck('siteUrl')->all();
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['service_account_json' => $e->getMessage()]);
        }
        if (! in_array($account->site_url, $sites, true)) {
            throw ValidationException::withMessages(['site_url' => "A conta de serviço não tem acesso a {$account->site_url}. Adicione {$account->service_account_email} como usuário da propriedade."]);
        }

        $account->forceFill(['is_active' => true, 'last_error' => null])->save();
        SyncSearchConsole::dispatch($account->tenant_id);

        return $this->success($this->present($account), 'Search Console conectado. A importação começou.');
    }

    public function deactivate(): JsonResponse
    {
        $account = $this->account();
        $account->forceFill(['is_active' => false])->save();

        return $this->success($this->present($account), 'Integração desativada.');
    }

    public function sync(): JsonResponse
    {
        $account = $this->account();
        if (! $account->is_active) {
            throw ValidationException::withMessages(['site_url' => 'Ative a integração antes de sincronizar.']);
        }
        SyncSearchConsole::dispatch($account->tenant_id);

        return $this->success(null, 'Sincronização iniciada.');
    }

    private function account(): SearchConsoleAccount
    {
        return SearchConsoleAccount::query()->firstOrNew([], ['tenant_id' => TenantContext::tenantId()]);
    }

    private function present(SearchConsoleAccount $a): array
    {
        return [
            'site_url' => $a->site_url,
            'service_account_email' => $a->service_account_email,
            'has_credentials' => filled($a->service_account_json),
            'is_active' => (bool) $a->is_active,
            'last_sync_at' => $a->last_sync_at?->toIso8601String(),
            'last_error' => $a->last_error,
        ];
    }
}
