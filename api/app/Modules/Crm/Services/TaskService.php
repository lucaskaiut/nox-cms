<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\CrmSettings;
use App\Modules\Crm\Models\LeadActivity;
use App\Modules\Crm\Models\LeadTask;
use App\Modules\Lead\Enums\LeadStatus;
use App\Modules\Lead\Models\Lead;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/** Tarefas de follow-up e leads esquecidos. */
class TaskService
{
    public function __construct(private readonly PipelineService $pipeline) {}

    /** @return Collection<int, LeadTask> */
    public function list(array $filters, User $user): Collection
    {
        $status = Arr::get($filters, 'status', 'open');

        return LeadTask::query()->with(['lead.stage', 'assignee'])
            ->whereHas('lead')
            ->when(Arr::get($filters, 'scope', 'mine') === 'mine', fn (Builder $q) => $q->where(fn ($q) => $q
                ->where('assigned_to', $user->id)->orWhere(fn ($q) => $q->whereNull('assigned_to')->where('created_by', $user->id))))
            ->when(Arr::get($filters, 'lead'), fn (Builder $q, $v) => $q->whereHas('lead', fn ($q) => $q->where('uuid', $v)))
            ->when($status === 'open', fn (Builder $q) => $q->whereNull('completed_at')->orderBy('due_at'))
            ->when($status === 'done', fn (Builder $q) => $q->whereNotNull('completed_at')->orderByDesc('completed_at'))
            ->when($status === 'all', fn (Builder $q) => $q->orderByRaw('completed_at IS NOT NULL')->orderBy('due_at'))
            ->limit(300)->get();
    }

    public function create(Lead $lead, array $data, ?User $user): LeadTask
    {
        $task = LeadTask::query()->create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'assigned_to' => $this->assigneeId($data['assigned_to'] ?? null) ?? $user?->id,
            'created_by' => $user?->id,
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'due_at' => Carbon::parse($data['due_at']),
        ]);

        $this->pipeline->log($lead, LeadActivity::TASK_CREATED, $task->title, [
            'task' => $task->uuid, 'due_at' => $task->due_at->toIso8601String(),
        ], $user);

        return $task->load(['lead.stage', 'assignee']);
    }

    public function update(LeadTask $task, array $data, ?User $user): LeadTask
    {
        $task->fill(Arr::only($data, ['title', 'notes']));
        if (array_key_exists('due_at', $data) && $data['due_at']) {
            $task->due_at = Carbon::parse($data['due_at']);
            $task->reminded_at = null;
        }
        if (array_key_exists('assigned_to', $data)) {
            $task->assigned_to = $this->assigneeId($data['assigned_to']);
        }

        $completing = array_key_exists('completed', $data) && (bool) $data['completed'] && $task->completed_at === null;
        if (array_key_exists('completed', $data)) {
            $task->completed_at = $data['completed'] ? ($task->completed_at ?? now()) : null;
        }
        $task->save();

        if ($completing && $task->lead) {
            $this->pipeline->log($task->lead, LeadActivity::TASK_COMPLETED, $task->title, ['task' => $task->uuid], $user);
        }

        return $task->load(['lead.stage', 'assignee']);
    }

    /** Leads em aberto sem atividade há mais de N dias (configurável). */
    public function staleLeads(?int $days = null, int $limit = 100): Collection
    {
        $days ??= CrmSettings::current()->stale_lead_days;

        return Lead::query()->with(['stage', 'tags'])
            ->whereIn('status', [LeadStatus::NEW->value, LeadStatus::QUALIFIED->value])
            ->where(fn ($q) => $q->where('last_activity_at', '<', now()->subDays($days))
                ->orWhere(fn ($q) => $q->whereNull('last_activity_at')->where('created_at', '<', now()->subDays($days))))
            ->whereDoesntHave('tasks', fn ($q) => $q->whereNull('completed_at')->where('due_at', '>=', now()))
            ->orderBy('last_activity_at')
            ->limit($limit)->get();
    }

    /** @return array{new_leads: int, overdue: \Illuminate\Support\Collection, today: \Illuminate\Support\Collection, stale: \Illuminate\Support\Collection} */
    public function digest(string $timezone): array
    {
        $now = now($timezone);
        $open = LeadTask::query()->with('lead')->whereNull('completed_at')->whereHas('lead');

        return [
            'new_leads' => Lead::query()->where('status', '!=', LeadStatus::SPAM->value)
                ->whereBetween('created_at', [$now->copy()->subDay()->startOfDay()->utc(), $now->copy()->subDay()->endOfDay()->utc()])->count(),
            'overdue' => (clone $open)->where('due_at', '<', $now->copy()->startOfDay()->utc())->orderBy('due_at')->get(),
            'today' => (clone $open)->whereBetween('due_at', [$now->copy()->startOfDay()->utc(), $now->copy()->endOfDay()->utc()])->orderBy('due_at')->get(),
            'stale' => $this->staleLeads(),
        ];
    }

    private function assigneeId(?string $uuid): ?int
    {
        return $uuid ? User::query()->where('uuid', $uuid)->value('id') : null;
    }
}
