<?php

namespace App\Modules\GoogleAds\Services;

use App\Modules\Crm\Models\LeadActivity;
use App\Modules\Crm\Services\PipelineService;
use App\Modules\GoogleAds\Models\GoogleAdsAccount;
use App\Modules\GoogleAds\Models\LeadConversionUpload;
use App\Modules\Lead\Enums\LeadStatus;
use App\Modules\Lead\Models\Lead;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Conversões offline automáticas: quando o lead qualifica ou fecha, registra a
 * conversão na fila; o envio é feito em lote para a Google Ads API, com nova
 * tentativa a cada hora (o Google demora a reconhecer cliques recentes).
 */
class ConversionUploadService
{
    /** Erros que significam "já está lá": tratamos como enviado. */
    private const ALREADY_EXISTS = ['CLICK_CONVERSION_ALREADY_EXISTS', 'DUPLICATE_CLICK_CONVERSION_IN_REQUEST'];

    /** Erros sem chance de sucesso em nova tentativa. */
    private const PERMANENT = [
        'EXPIRED_CLICK', 'CONVERSION_PRECEDES_CLICK', 'INVALID_CONVERSION_ACTION', 'UNPARSEABLE_GCLID',
        'CLICK_NOT_FOUND_AFTER_RETRIES', 'CONVERSION_ACTION_NOT_FOUND', 'INVALID_CONVERSION_ACTION_TYPE',
    ];

    public function __construct(private readonly PipelineService $pipeline) {}

    public function account(int $tenantId): ?GoogleAdsAccount
    {
        return GoogleAdsAccount::query()->forTenant($tenantId)->first();
    }

    /** Coloca na fila as conversões que o lead já atingiu. Retorna quantas entraram. */
    public function queue(Lead $lead): int
    {
        $account = $this->account($lead->tenant_id);
        if (! $account?->is_active || ! $account->isConfigured() || ! ($lead->gclid || $lead->gbraid || $lead->wbraid)) {
            return 0;
        }

        $queued = 0;
        if ($lead->qualified_at && filled($account->qualified_action_id)) {
            $value = filled($account->won_action_id) ? null : $lead->conversion_value;
            $queued += (int) $this->upsert($lead, LeadConversionUpload::QUALIFIED, $lead->qualified_at, $value);
        }
        if ($lead->status === LeadStatus::WON && $lead->won_at && filled($account->won_action_id)) {
            $queued += (int) $this->upsert($lead, LeadConversionUpload::WON, $lead->won_at, $lead->conversion_value);
        }

        return $queued;
    }

    private function upsert(Lead $lead, string $kind, Carbon $at, mixed $value): bool
    {
        $windowDays = (int) config('leads.google_ads.click_window_days');
        $clickAt = $lead->clicked_at ?? $lead->created_at;
        if ($clickAt && $clickAt->lt($at->copy()->subDays($windowDays))) {
            return false; // clique fora da janela de 90 dias
        }

        $upload = LeadConversionUpload::query()->forTenant($lead->tenant_id)->firstOrNew(['lead_id' => $lead->id, 'kind' => $kind]);
        if ($upload->exists && $upload->status === LeadConversionUpload::UPLOADED) {
            return false; // o Google não aceita reenvio/ajuste por esta via
        }

        $upload->fill([
            'tenant_id' => $lead->tenant_id, 'status' => LeadConversionUpload::PENDING,
            'conversion_at' => $at, 'value' => $value, 'attempts' => $upload->exists ? $upload->attempts : 0,
        ])->save();

        return true;
    }

    /** Envia as pendências de um tenant. Retorna [enviadas, falhas]. */
    public function processPending(int $tenantId): array
    {
        $account = $this->account($tenantId);
        if (! $account?->is_active || ! $account->isConfigured()) {
            return [0, 0];
        }

        $client = new GoogleAdsClient($account);
        $uploaded = $failed = 0;
        $timezone = (string) config('leads.google_ads.timezone');

        LeadConversionUpload::query()->forTenant($tenantId)->with(['lead' => fn ($q) => $q->withoutTenancy()])
            ->where('status', LeadConversionUpload::PENDING)
            ->orderBy('id')
            ->chunkById((int) config('leads.google_ads_api.batch_size', 200), function ($uploads) use ($client, $account, $timezone, &$uploaded, &$failed) {
                $uploads = $uploads->filter(fn ($u) => $u->lead !== null)->values();
                if ($uploads->isEmpty()) return;

                $payload = $uploads->map(fn (LeadConversionUpload $u) => $this->conversionPayload($u, $client, $account, $timezone))->all();

                try {
                    $response = $client->uploadClickConversions($payload);
                } catch (Throwable $e) {
                    // Falha da requisição inteira (credencial, rede): conta tentativa em todas.
                    foreach ($uploads as $u) {
                        $this->markAttempt($u, $e->getMessage());
                    }
                    $account->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 2000)])->save();
                    $failed += $uploads->count();

                    return false;
                }

                foreach ($uploads as $index => $u) {
                    $error = $response['errors'][$index] ?? null;
                    if ($error === null || in_array($error['code'], self::ALREADY_EXISTS, true)) {
                        $u->forceFill(['status' => LeadConversionUpload::UPLOADED, 'uploaded_at' => now(), 'error' => null, 'attempts' => $u->attempts + 1])->save();
                        $this->pipeline->log($u->lead, LeadActivity::CONVERSION_UPLOADED, null, ['kind' => $u->kind, 'value' => $u->value]);
                        $uploaded++;
                    } else {
                        $this->markAttempt($u, "{$error['code']}: {$error['message']}", in_array($error['code'], self::PERMANENT, true));
                        $failed++;
                    }
                }

                $account->forceFill(['last_upload_at' => now(), 'last_error' => null])->save();
            });

        return [$uploaded, $failed];
    }

    private function conversionPayload(LeadConversionUpload $u, GoogleAdsClient $client, GoogleAdsAccount $account, string $timezone): array
    {
        $lead = $u->lead;
        $actionId = $u->kind === LeadConversionUpload::WON ? $account->won_action_id : $account->qualified_action_id;
        $conversion = [
            'conversionAction' => $client->conversionActionResource((string) $actionId),
            'conversionDateTime' => $u->conversion_at->copy()->setTimezone($timezone)->format('Y-m-d H:i:sP'),
        ];

        // Um único identificador de clique por conversão (prioridade: gclid).
        if ($lead->gclid) {
            $conversion['gclid'] = $lead->gclid;
        } elseif ($lead->gbraid) {
            $conversion['gbraid'] = $lead->gbraid;
        } else {
            $conversion['wbraid'] = $lead->wbraid;
        }

        if ($u->value !== null) {
            $conversion['conversionValue'] = (float) $u->value;
            $conversion['currencyCode'] = $account->currency ?: 'BRL';
        }

        if (in_array($lead->consent, ['granted', 'denied'], true)) {
            $conversion['consent'] = ['adUserData' => strtoupper($lead->consent)];
        }

        return $conversion;
    }

    private function markAttempt(LeadConversionUpload $u, string $error, bool $permanent = false): void
    {
        $attempts = $u->attempts + 1;
        $u->forceFill([
            'attempts' => $attempts,
            'error' => mb_substr($error, 0, 2000),
            'status' => $permanent || $attempts >= LeadConversionUpload::MAX_ATTEMPTS ? LeadConversionUpload::FAILED : LeadConversionUpload::PENDING,
        ])->save();
    }
}
