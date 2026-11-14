<?php

namespace App\Modules\GoogleAds\Http\Controllers;

use App\Modules\GoogleAds\Jobs\UploadConversions;
use App\Modules\GoogleAds\Models\GoogleAdsAccount;
use App\Modules\GoogleAds\Models\LeadConversionUpload;
use App\Modules\GoogleAds\Services\ConversionUploadService;
use App\Modules\GoogleAds\Services\GoogleAdsClient;
use App\Modules\Lead\Models\Lead;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Conexão com a Google Ads API e fila de conversões offline. */
class GoogleAdsController extends ApiController
{
    public function __construct(private readonly ConversionUploadService $service) {}

    public function show(): JsonResponse
    {
        return $this->success($this->present($this->account()));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'string', 'max:20', 'regex:/^[\d-]{10,12}$/'],
            'login_customer_id' => ['nullable', 'string', 'max:20', 'regex:/^[\d-]{10,12}$/'],
            'developer_token' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'refresh_token' => ['nullable', 'string', 'max:1024'],
            'qualified_action_id' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'won_action_id' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'currency' => ['nullable', 'string', 'size:3'],
        ], ['customer_id.regex' => 'Use o ID de 10 dígitos da conta (ex.: 123-456-7890).']);

        foreach (['developer_token', 'client_secret', 'refresh_token'] as $secret) {
            if (blank($data[$secret] ?? null)) unset($data[$secret]); // em branco = manter
        }
        foreach (['customer_id', 'login_customer_id'] as $id) {
            if (array_key_exists($id, $data)) $data[$id] = $data[$id] ? GoogleAdsClient::digits($data[$id]) : null;
        }
        if (isset($data['currency'])) $data['currency'] = strtoupper($data['currency']);

        $account = $this->account();
        $account->fill($data)->save();

        return $this->success($this->present($account), 'Configuração salva.');
    }

    /** Valida as credenciais (lista as ações de conversão) e ativa o envio. */
    public function activate(): JsonResponse
    {
        $account = $this->account();
        if (! $account->isConfigured()) {
            throw ValidationException::withMessages(['customer_id' => 'Preencha conta, credenciais e pelo menos uma ação de conversão.']);
        }

        try {
            $actions = collect((new GoogleAdsClient($account))->conversionActions())->pluck('id')->all();
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['developer_token' => $e->getMessage()]);
        }

        foreach (['qualified_action_id' => 'qualificado', 'won_action_id' => 'ganho'] as $field => $label) {
            if (filled($account->{$field}) && ! in_array($account->{$field}, $actions, true)) {
                throw ValidationException::withMessages([$field => "A ação de conversão de {$label} não existe na conta ou não é do tipo \"importação de cliques\"."]);
            }
        }

        $account->forceFill(['is_active' => true, 'last_error' => null])->save();

        // Leads que já qualificaram/fecharam desde que a conexão foi ativada entram na fila.
        Lead::query()->whereNotNull('qualified_at')->where('created_at', '>=', now()->subDays((int) config('leads.google_ads.click_window_days')))
            ->where(fn ($q) => $q->whereNotNull('gclid')->orWhereNotNull('gbraid')->orWhereNotNull('wbraid'))
            ->each(fn (Lead $lead) => $this->service->queue($lead));
        UploadConversions::dispatch($account->tenant_id);

        return $this->success($this->present($account->refresh()), 'Google Ads conectado. As conversões serão enviadas automaticamente.');
    }

    public function deactivate(): JsonResponse
    {
        $account = $this->account();
        $account->forceFill(['is_active' => false])->save();

        return $this->success($this->present($account), 'Envio automático desativado.');
    }

    public function conversionActions(): JsonResponse
    {
        try {
            return $this->success((new GoogleAdsClient($this->account()))->conversionActions());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['developer_token' => $e->getMessage()]);
        }
    }

    public function uploads(Request $request): JsonResponse
    {
        $uploads = LeadConversionUpload::query()->with('lead:id,uuid,ref,name')
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->latest('id')->limit(100)->get()
            ->map(fn (LeadConversionUpload $u) => [
                'id' => $u->id, 'kind' => $u->kind, 'status' => $u->status, 'value' => $u->value,
                'conversion_at' => $u->conversion_at?->toIso8601String(), 'uploaded_at' => $u->uploaded_at?->toIso8601String(),
                'attempts' => $u->attempts, 'error' => $u->error,
                'lead' => $u->lead ? ['id' => $u->lead->uuid, 'ref' => $u->lead->ref, 'name' => $u->lead->name] : null,
            ]);

        return $this->success($uploads);
    }

    public function retry(): JsonResponse
    {
        $count = LeadConversionUpload::query()->where('status', LeadConversionUpload::FAILED)
            ->update(['status' => LeadConversionUpload::PENDING, 'attempts' => 0]);
        UploadConversions::dispatch(TenantContext::tenantId());

        return $this->success(['requeued' => $count], "{$count} conversão(ões) recolocada(s) na fila.");
    }

    private function account(): GoogleAdsAccount
    {
        return GoogleAdsAccount::query()->firstOrNew([], ['tenant_id' => TenantContext::tenantId()]);
    }

    private function present(GoogleAdsAccount $a): array
    {
        $counts = LeadConversionUpload::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'customer_id' => $a->customer_id,
            'login_customer_id' => $a->login_customer_id,
            'client_id' => $a->client_id,
            'has_developer_token' => filled($a->developer_token),
            'has_client_secret' => filled($a->client_secret),
            'has_refresh_token' => filled($a->refresh_token),
            'qualified_action_id' => $a->qualified_action_id,
            'won_action_id' => $a->won_action_id,
            'currency' => $a->currency ?? 'BRL',
            'is_active' => (bool) $a->is_active,
            'is_configured' => $a->isConfigured(),
            'last_upload_at' => $a->last_upload_at?->toIso8601String(),
            'last_error' => $a->last_error,
            'api_version' => config('leads.google_ads_api.version'),
            'counts' => [
                'pending' => (int) ($counts['pending'] ?? 0),
                'uploaded' => (int) ($counts['uploaded'] ?? 0),
                'failed' => (int) ($counts['failed'] ?? 0),
            ],
        ];
    }
}
