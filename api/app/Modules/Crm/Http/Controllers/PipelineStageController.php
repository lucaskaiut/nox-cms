<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Modules\Crm\Http\Requests\StorePipelineStageRequest;
use App\Modules\Crm\Http\Resources\PipelineStageResource;
use App\Modules\Crm\Models\PipelineStage;
use App\Modules\Crm\Services\PipelineService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Cadastro das etapas do funil (colunas do kanban). */
class PipelineStageController extends ApiController
{
    public function __construct(private readonly PipelineService $pipeline) {}

    public function index(): JsonResponse
    {
        return $this->success(PipelineStageResource::collection($this->pipeline->stages()));
    }

    public function store(StorePipelineStageRequest $request): JsonResponse
    {
        $stage = $this->pipeline->create($request->validated());

        return $this->created(PipelineStageResource::make($stage->loadCount('leads')), 'Etapa criada.');
    }

    public function update(StorePipelineStageRequest $request, PipelineStage $stage): JsonResponse
    {
        return $this->success(PipelineStageResource::make($this->pipeline->update($stage, $request->validated())), 'Etapa atualizada.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['uuid', 'distinct']]);

        return $this->success(PipelineStageResource::collection($this->pipeline->reorder($data['ids'])), 'Ordem salva.');
    }

    public function destroy(Request $request, PipelineStage $stage): JsonResponse
    {
        $data = $request->validate(['move_to' => ['nullable', 'uuid']]);
        $moveTo = isset($data['move_to']) ? PipelineStage::query()->where('uuid', $data['move_to'])->firstOrFail() : null;
        $this->pipeline->delete($stage, $moveTo);

        return $this->success(null, 'Etapa removida.');
    }
}
