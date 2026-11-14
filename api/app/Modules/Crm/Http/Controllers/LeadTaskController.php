<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Crm\Http\Resources\LeadTaskResource;
use App\Modules\Crm\Models\LeadTask;
use App\Modules\Crm\Services\TaskService;
use App\Modules\Lead\Http\Resources\LeadResource;
use App\Modules\Lead\Models\Lead;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Tarefas de follow-up, leads parados e responsáveis possíveis. */
class LeadTaskController extends ApiController
{
    public function __construct(private readonly TaskService $tasks) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'scope' => ['nullable', 'in:mine,all'],
            'status' => ['nullable', 'in:open,done,all'],
            'lead' => ['nullable', 'uuid'],
        ]);
        // Sem usuário (token de API) não existe "minhas".
        if (! $request->user() instanceof User) {
            $filters['scope'] = 'all';
        }

        return $this->success(LeadTaskResource::collection($this->tasks->list($filters, $request->user() ?? new User)));
    }

    public function store(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate($this->rules(true));

        return $this->created(LeadTaskResource::make($this->tasks->create($lead, $data, $this->user($request))), 'Tarefa criada.');
    }

    public function update(Request $request, LeadTask $task): JsonResponse
    {
        $data = $request->validate($this->rules(false) + ['completed' => ['sometimes', 'boolean']]);

        return $this->success(LeadTaskResource::make($this->tasks->update($task, $data, $this->user($request))), 'Tarefa atualizada.');
    }

    public function destroy(LeadTask $task): JsonResponse
    {
        $task->delete();

        return $this->success(null, 'Tarefa removida.');
    }

    public function stale(Request $request): JsonResponse
    {
        $days = $request->integer('days') ?: null;

        return $this->success(LeadResource::collection($this->tasks->staleLeads($days)));
    }

    public function assignees(): JsonResponse
    {
        $users = User::query()->orderBy('name')->get()
            ->filter(fn (User $user) => $user->hasPermission(Permission::LEAD_UPDATE))
            ->map(fn (User $user) => ['id' => $user->uuid, 'name' => $user->name])->values();

        return $this->success($users);
    }

    private function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$req, 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_at' => [$req, 'date'],
            'assigned_to' => ['nullable', 'uuid'],
        ];
    }

    private function user(Request $request): ?User
    {
        return $request->user() instanceof User ? $request->user() : null;
    }
}
