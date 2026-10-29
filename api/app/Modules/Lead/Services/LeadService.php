<?php

namespace App\Modules\Lead\Services;

use App\Modules\Lead\Enums\LeadStatus;
use App\Modules\Lead\Models\Lead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LeadService
{
    private const ATTRIBUTION_FIELDS = [
        'gclid', 'gbraid', 'wbraid', 'utm_source', 'utm_medium', 'utm_campaign',
        'utm_term', 'utm_content', 'landing_path',
    ];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Lead::query()
            ->when(Arr::get($filters, 'status'), fn ($q, $v) => $q->where('status', $v))
            ->when(Arr::get($filters, 'ref'), fn ($q, $v) => $q->where('ref', strtoupper($v)))
            ->orderByDesc('created_at')->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Registro idempotente pelo `ref`: o site reenvia o mesmo código a cada
     * clique na sessão. A atribuição do primeiro registro é preservada.
     */
    public function register(array $data): Lead
    {
        $lead = Lead::query()->firstOrNew(['ref' => $data['ref']]);

        if (! $lead->exists) {
            $lead->fill(Arr::only($data, [...self::ATTRIBUTION_FIELDS, 'ref', 'channel']));
            $lead->status = LeadStatus::NEW;
            $lead->clicked_at = Arr::get($data, 'captured_at');
        }

        $lead->click_path = $data['click_path'] ?? $lead->click_path;
        $lead->consent = $data['consent'] ?? $lead->consent ?? 'unknown';
        $lead->contacted_at = now();

        try {
            $lead->save();
        } catch (UniqueConstraintViolationException $e) {
            // Dois cliques simultâneos com o mesmo código: o outro já criou o registro.
            if ($lead->exists) throw $e;

            return $this->register($data);
        }

        return $lead;
    }

    public function update(Lead $lead, array $data): Lead
    {
        $lead->fill(Arr::only($data, ['status', 'notes', 'conversion_value']));

        $status = $lead->status;
        if ($status instanceof LeadStatus && $status->isQualified() && $lead->qualified_at === null) {
            $lead->qualified_at = now();
        }

        $lead->save();

        return $lead->refresh();
    }

    /**
     * Leads qualificados com GCLID dentro da janela aceita pelo Google Ads.
     *
     * @return Collection<int, Lead>
     */
    public function qualifiedForGoogleAds(?Carbon $since = null): Collection
    {
        $windowDays = (int) config('leads.google_ads.click_window_days');

        return Lead::query()
            ->whereIn('status', [LeadStatus::QUALIFIED->value, LeadStatus::WON->value])
            ->whereNotNull('gclid')
            ->whereNotNull('qualified_at')
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->when($since, fn ($q, $v) => $q->where('qualified_at', '>=', $v))
            ->orderBy('qualified_at')
            ->get();
    }

    /**
     * CSV no modelo de importação de conversões por clique do Google Ads.
     */
    public function googleAdsCsv(Collection $leads): string
    {
        $config = config('leads.google_ads');
        $handle = fopen('php://temp', 'r+');

        fwrite($handle, "Parameters:TimeZone={$config['timezone']}\n");
        fputcsv($handle, ['Google Click ID', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency', 'Ad User Data'], escape: '');

        foreach ($leads as $lead) {
            fputcsv($handle, [
                $lead->gclid,
                $config['conversion_name'],
                $lead->qualified_at->clone()->setTimezone($config['timezone'])->format('Y-m-d H:i:s'),
                $lead->conversion_value ?? '',
                $lead->conversion_value !== null ? $config['currency'] : '',
                match ($lead->consent) {
                    'granted' => 'GRANTED',
                    'denied' => 'DENIED',
                    default => '',
                },
            ], escape: '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
