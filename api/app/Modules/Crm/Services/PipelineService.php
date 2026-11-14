<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Enums\StageOutcome;
use App\Modules\Crm\Models\LeadActivity;
use App\Modules\Crm\Models\PipelineStage;
use App\Modules\Lead\Enums\LeadStatus;
use App\Modules\Lead\Events\LeadCreated;
use App\Modules\Lead\Events\LeadStatusChanged;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Support\LeadRef;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PipelineService
{
    public const DEFAULT_STAGES = [
        ['Novo', 'slate', StageOutcome::OPEN],
        ['Em conversa', 'blue', StageOutcome::OPEN],
        ['Qualificado', 'amber', StageOutcome::QUALIFIED],
        ['Proposta', 'violet', StageOutcome::QUALIFIED],
        ['Fechado', 'green', StageOutcome::WON],
        ['Perdido', 'red', StageOutcome::LOST],
    ];

    /** Cartões por coluna no quadro; o resto aparece na contagem. */
    public const BOARD_LIMIT = 100;

    /** @return Collection<int, PipelineStage> */
    public function stages(): Collection
    {
        $this->ensureDefaults();

        return PipelineStage::query()->withCount('leads')->orderBy('position')->orderBy('id')->get();
    }

    /** Tenants novos ganham o funil padrão no primeiro acesso. */
    public function ensureDefaults(): void
    {
        if (PipelineStage::query()->exists()) {
            return;
        }

        foreach (self::DEFAULT_STAGES as $position => [$name, $color, $outcome]) {
            PipelineStage::query()->create(compact('name', 'color', 'position') + ['outcome' => $outcome]);
        }
    }

    public function firstStage(StageOutcome $outcome = StageOutcome::OPEN): ?PipelineStage
    {
        $this->ensureDefaults();

        return PipelineStage::query()->where('outcome', $outcome)->orderBy('position')->first()
            ?? ($outcome === StageOutcome::OPEN ? PipelineStage::query()->orderBy('position')->first() : null);
    }

    public function create(array $data): PipelineStage
    {
        $this->ensureDefaults();
        $data['position'] ??= (int) PipelineStage::query()->max('position') + 1;

        return PipelineStage::query()->create(Arr::only($data, ['name', 'color', 'outcome', 'position']));
    }

    public function update(PipelineStage $stage, array $data): PipelineStage
    {
        $stage->fill(Arr::only($data, ['name', 'color', 'outcome']))->save();

        if ($stage->wasChanged('outcome')) {
            // Mantém o status dos leads coerente com o novo significado da etapa.
            $status = $stage->outcome->leadStatus();
            $stage->leads()->where('status', '!=', LeadStatus::SPAM->value)->update(['status' => $status->value]);
            if ($status->isQualified()) {
                $stage->leads()->whereNull('qualified_at')->update(['qualified_at' => now()]);
            }
            if ($status === LeadStatus::WON) {
                $stage->leads()->whereNull('won_at')->update(['won_at' => now()]);
            }
            if ($status === LeadStatus::LOST) {
                $stage->leads()->whereNull('lost_at')->update(['lost_at' => now()]);
            }
        }

        return $stage->refresh()->loadCount('leads');
    }

    /** @param list<string> $uuids ordem completa das etapas */
    public function reorder(array $uuids): Collection
    {
        $stages = PipelineStage::query()->whereIn('uuid', $uuids)->get()->keyBy('uuid');
        if ($stages->count() !== count($uuids) || $stages->count() !== PipelineStage::query()->count()) {
            throw ValidationException::withMessages(['ids' => 'Envie todas as etapas do funil, cada uma uma vez.']);
        }

        DB::transaction(function () use ($uuids, $stages) {
            foreach ($uuids as $position => $uuid) {
                $stages[$uuid]->update(['position' => $position]);
            }
        });

        return $this->stages();
    }

    public function delete(PipelineStage $stage, ?PipelineStage $moveTo): void
    {
        if (PipelineStage::query()->count() <= 1) {
            throw ValidationException::withMessages(['stage' => 'O funil precisa de pelo menos uma etapa.']);
        }
        $hasLeads = $stage->leads()->exists();
        if ($hasLeads && ($moveTo === null || $moveTo->is($stage))) {
            throw ValidationException::withMessages(['move_to' => 'Escolha para qual etapa mover os leads desta etapa.']);
        }

        DB::transaction(function () use ($stage, $moveTo, $hasLeads) {
            if ($hasLeads) {
                $stage->leads()->each(fn (Lead $lead) => $this->moveLead($lead, $moveTo, null));
            }
            $stage->delete();
        });
    }

    /** @return Collection<int, PipelineStage> etapas com os cartões (sem spam) */
    public function board(?string $search = null): Collection
    {
        $stages = $this->stages();
        $search = trim((string) $search);

        foreach ($stages as $stage) {
            $query = Lead::query()
                ->where('stage_id', $stage->id)
                ->where('status', '!=', LeadStatus::SPAM->value)
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('ref', 'like', '%'.strtoupper($search).'%')
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")));

            $stage->setAttribute('total', (clone $query)->count());
            $stage->setRelation('leads', $query->with(['conversation', 'stage', 'tags'])
                ->withCount(['tasks as open_tasks_count' => fn ($q) => $q->whereNull('completed_at')])
                ->orderByDesc('last_activity_at')->orderByDesc('id')
                ->limit(self::BOARD_LIMIT)->get());
        }

        return $stages;
    }

    public function moveLead(Lead $lead, PipelineStage $stage, ?User $user, ?string $lostReason = null): Lead
    {
        if ($lead->stage_id === $stage->id && $lead->status !== LeadStatus::SPAM) {
            if ($lostReason !== null && $lead->status === LeadStatus::LOST && $lead->lost_reason !== $lostReason) {
                $lead->forceFill(['lost_reason' => $lostReason])->save();
                $this->log($lead, LeadActivity::NOTE, "Motivo da perda: {$lostReason}", null, $user);
            }

            return $lead;
        }

        $from = $lead->stage;
        $previousStatus = $lead->status;
        $lead->stage()->associate($stage);
        $lead->stage_changed_at = now();
        $this->applyStatus($lead, $stage->outcome->leadStatus(), $lostReason);
        $lead->save();

        $meta = ['from' => $from?->name, 'to' => $stage->name, 'outcome' => $stage->outcome->value];
        if ($lead->status === LeadStatus::LOST && $lead->lost_reason) {
            $meta['lost_reason'] = $lead->lost_reason;
        }
        $this->log($lead, LeadActivity::STAGE_CHANGED, null, $meta, $user);

        if ($previousStatus !== $lead->status) {
            LeadStatusChanged::dispatch($lead, $previousStatus);
        }

        return $lead->refresh()->load(['stage', 'conversation', 'tags', 'post']);
    }

    /**
     * Aplica um status e as datas que dependem dele (qualificado, ganho,
     * perdido). Não salva: quem chama decide quando persistir.
     */
    public function applyStatus(Lead $lead, LeadStatus $status, ?string $lostReason = null): void
    {
        $lead->status = $status;

        if ($status->isQualified() && $lead->qualified_at === null) {
            $lead->qualified_at = now();
        }

        if ($status === LeadStatus::WON) {
            $lead->won_at ??= now();
        }

        if ($status === LeadStatus::LOST) {
            if ($lead->getOriginal('status') !== LeadStatus::LOST || $lead->lost_at === null) {
                $lead->lost_at = now();
            }
            if ($lostReason !== null) {
                $lead->lost_reason = $lostReason;
            }
        } elseif ($lead->isDirty('status')) {
            // Reaberto/ganho: limpa a perda anterior para não poluir o relatório.
            $lead->lost_at = null;
            $lead->lost_reason = null;
        }
    }

    public function addNote(Lead $lead, string $body, ?User $user): LeadActivity
    {
        return $this->log($lead, LeadActivity::NOTE, $body, null, $user);
    }

    public function log(Lead $lead, string $type, ?string $body = null, ?array $meta = null, ?User $user = null): LeadActivity
    {
        $activity = LeadActivity::query()->create([
            'tenant_id' => $lead->tenant_id, 'lead_id' => $lead->id, 'user_id' => $user?->id,
            'type' => $type, 'body' => $body, 'meta' => $meta,
        ]);
        $lead->forceFill(['last_activity_at' => now()])->saveQuietly();

        return $activity;
    }

    /** Lead cadastrado à mão no painel (indicação, contato direto…). */
    public function createLead(array $data, ?User $user, string $channel = 'manual'): Lead
    {
        $stage = isset($data['stage_id'])
            ? PipelineStage::query()->where('uuid', $data['stage_id'])->firstOrFail()
            : $this->firstStage();

        $lead = new Lead(Arr::only($data, ['name', 'email', 'phone', 'company', 'message', 'notes']));
        $lead->ref = $this->uniqueRef();
        $lead->channel = $channel;
        $this->applyStatus($lead, $stage->outcome->leadStatus(), $data['lost_reason'] ?? null);
        $lead->stage()->associate($stage);
        $lead->stage_changed_at = now();
        $lead->contacted_at = now();
        $lead->save();

        $this->log($lead, LeadActivity::CREATED, null, ['channel' => $channel], $user);
        LeadCreated::dispatch($lead);

        return $lead->refresh()->load(['stage', 'conversation', 'tags', 'post']);
    }

    public function uniqueRef(): string
    {
        do {
            $ref = LeadRef::generate();
        } while (Lead::query()->where('ref', $ref)->exists());

        return $ref;
    }
}
