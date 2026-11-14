<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Models\AdSpend;
use App\Modules\Crm\Models\LeadActivity;
use App\Modules\Crm\Services\PipelineService;
use App\Modules\Lead\Enums\LeadStatus;
use App\Modules\Lead\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Números do funil num período: volume, conversão, receita, origem dos leads,
 * motivos de perda, tempo médio em cada etapa e custo por lead (anúncios).
 */
class FunnelReportService
{
    public const SOURCES = [
        'paid' => 'Anúncios', 'blog' => 'Blog (orgânico)', 'referral' => 'Outras campanhas',
        'whatsapp' => 'WhatsApp direto', 'direct' => 'Site (direto)', 'manual' => 'Cadastro manual',
    ];

    public function __construct(private readonly PipelineService $pipeline) {}

    public function summary(Carbon $from, Carbon $to): array
    {
        $days = max(1, (int) round($from->diffInHours($to) / 24));
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

        $current = $this->totals($from, $to);
        $previous = $this->totals($prevFrom, $prevTo);
        $spend = $this->spend($from, $to);
        $created = $this->createdIn($from, $to);
        $paid = $created->filter(fn (Lead $l) => $l->source() === 'paid');
        $paidWon = $this->wonIn($from, $to)->filter(fn (Lead $l) => $l->source() === 'paid');

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $days],
            'previous_period' => ['from' => $prevFrom->toDateString(), 'to' => $prevTo->toDateString()],
            'totals' => $current,
            'previous' => $previous,
            'series' => $this->series($from, $to),
            'cohort' => [
                'created' => $created->count(),
                'qualified' => $created->whereNotNull('qualified_at')->count(),
                'won' => $created->where('status', LeadStatus::WON)->count(),
            ],
            'by_source' => $this->bySource($from, $to, $created),
            'lost_reasons' => $this->lostIn($from, $to)->groupBy(fn (Lead $l) => $l->lost_reason ?: 'Sem motivo informado')
                ->map(fn (Collection $g, $reason) => ['reason' => $reason, 'count' => $g->count(), 'value' => (float) $g->sum('conversion_value')])
                ->sortByDesc('count')->values(),
            'stages' => $this->stages(),
            'spend' => [
                'total' => round($spend, 2),
                'paid_leads' => $paid->count(),
                'paid_won' => $paidWon->count(),
                'paid_revenue' => round((float) $paidWon->sum('conversion_value'), 2),
                'cost_per_lead' => $paid->count() > 0 && $spend > 0 ? round($spend / $paid->count(), 2) : null,
                'cost_per_won' => $paidWon->count() > 0 && $spend > 0 ? round($spend / $paidWon->count(), 2) : null,
                'roas' => $spend > 0 ? round((float) $paidWon->sum('conversion_value') / $spend, 2) : null,
            ],
        ];
    }

    private function totals(Carbon $from, Carbon $to): array
    {
        $won = $this->wonIn($from, $to);
        $lost = $this->lostIn($from, $to);
        $closed = $won->count() + $lost->count();
        $revenue = (float) $won->sum('conversion_value');
        $daysToWin = $won->map(fn (Lead $l) => $l->created_at && $l->won_at ? $l->created_at->diffInHours($l->won_at) / 24 : null)->filter(fn ($v) => $v !== null);

        return [
            'leads' => $this->createdIn($from, $to)->count(),
            'qualified' => Lead::query()->whereBetween('qualified_at', [$from, $to])->count(),
            'won' => $won->count(),
            'lost' => $lost->count(),
            'revenue' => round($revenue, 2),
            'avg_ticket' => $won->whereNotNull('conversion_value')->count() > 0 ? round($revenue / $won->whereNotNull('conversion_value')->count(), 2) : null,
            'win_rate' => $closed > 0 ? round($won->count() / $closed * 100, 1) : null,
            'avg_days_to_win' => $daysToWin->isNotEmpty() ? round($daysToWin->avg(), 1) : null,
            'open_pipeline_value' => round((float) Lead::query()->whereIn('status', [LeadStatus::NEW->value, LeadStatus::QUALIFIED->value])->sum('conversion_value'), 2),
        ];
    }

    /** @return Collection<int, Lead> */
    private function createdIn(Carbon $from, Carbon $to): Collection
    {
        return Lead::query()->where('status', '!=', LeadStatus::SPAM->value)->whereBetween('created_at', [$from, $to])->get();
    }

    private function wonIn(Carbon $from, Carbon $to): Collection
    {
        return Lead::query()->where('status', LeadStatus::WON->value)->whereBetween('won_at', [$from, $to])->get();
    }

    private function lostIn(Carbon $from, Carbon $to): Collection
    {
        return Lead::query()->where('status', LeadStatus::LOST->value)->whereBetween('lost_at', [$from, $to])->get();
    }

    private function series(Carbon $from, Carbon $to): array
    {
        $tz = (string) config('leads.google_ads.timezone');
        $created = $this->createdIn($from, $to)->countBy(fn (Lead $l) => $l->created_at->copy()->setTimezone($tz)->toDateString());
        $won = $this->wonIn($from, $to)->countBy(fn (Lead $l) => $l->won_at->copy()->setTimezone($tz)->toDateString());

        $rows = [];
        for ($day = $from->copy()->setTimezone($tz)->startOfDay(); $day->lte($to->copy()->setTimezone($tz)); $day->addDay()) {
            $key = $day->toDateString();
            $rows[] = ['date' => $key, 'leads' => $created[$key] ?? 0, 'won' => $won[$key] ?? 0];
        }

        return $rows;
    }

    private function bySource(Carbon $from, Carbon $to, Collection $created): array
    {
        $won = $this->wonIn($from, $to)->groupBy(fn (Lead $l) => $l->source());
        $createdBy = $created->groupBy(fn (Lead $l) => $l->source());

        return collect(self::SOURCES)->map(fn ($label, $key) => [
            'source' => $key,
            'label' => $label,
            'leads' => ($createdBy[$key] ?? collect())->count(),
            'qualified' => ($createdBy[$key] ?? collect())->whereNotNull('qualified_at')->count(),
            'won' => ($won[$key] ?? collect())->count(),
            'revenue' => round((float) ($won[$key] ?? collect())->sum('conversion_value'), 2),
        ])->filter(fn ($row) => $row['leads'] || $row['won'])->sortByDesc('leads')->values()->all();
    }

    /**
     * Situação atual por etapa e tempo médio que os leads passaram em cada uma
     * (pelas mudanças de etapa dos últimos 180 dias).
     */
    private function stages(): array
    {
        $durations = [];
        $since = now()->subDays(180);
        Lead::query()->where('created_at', '>=', $since)->select(['id', 'created_at'])
            ->with(['activities' => fn ($q) => $q->where('type', LeadActivity::STAGE_CHANGED)->orderBy('id')])
            ->chunkById(500, function ($leads) use (&$durations) {
                foreach ($leads as $lead) {
                    $enteredAt = $lead->created_at;
                    foreach ($lead->activities as $change) {
                        $stage = $change->meta['from'] ?? null;
                        if ($stage && $enteredAt) {
                            $durations[$stage][] = $enteredAt->diffInHours($change->created_at) / 24;
                        }
                        $enteredAt = $change->created_at;
                    }
                }
            });

        return $this->pipeline->stages()->map(function ($stage) use ($durations) {
            $open = $stage->leads()->where('status', '!=', LeadStatus::SPAM->value);
            $times = $durations[$stage->name] ?? [];

            return [
                'id' => $stage->uuid,
                'name' => $stage->name,
                'color' => $stage->color,
                'outcome' => $stage->outcome->value,
                'count' => (clone $open)->count(),
                'value' => round((float) (clone $open)->sum('conversion_value'), 2),
                'avg_days' => $times ? round(array_sum($times) / count($times), 1) : null,
                'samples' => count($times),
            ];
        })->values()->all();
    }

    /** Investimento proporcional aos dias do período que cada lançamento cobre. */
    public function spend(Carbon $from, Carbon $to, ?string $channel = null): float
    {
        $tz = (string) config('leads.google_ads.timezone');
        $fromDate = $from->copy()->setTimezone($tz)->startOfDay()->setTimezone('UTC')->startOfDay();
        $toDate = $to->copy()->setTimezone($tz)->startOfDay()->setTimezone('UTC')->startOfDay();

        return (float) AdSpend::query()
            ->when($channel, fn ($q, $v) => $q->where('channel', $v))
            ->where('period_start', '<=', $toDate->toDateString())->where('period_end', '>=', $fromDate->toDateString())
            ->get()->sum(function (AdSpend $s) use ($fromDate, $toDate) {
                $total = (int) round($s->period_start->diffInDays($s->period_end)) + 1;
                $start = $s->period_start->max($fromDate);
                $end = $s->period_end->min($toDate);
                $overlap = (int) round($start->diffInDays($end)) + 1;

                return $total > 0 ? (float) $s->amount * $overlap / $total : 0;
            });
    }
}
