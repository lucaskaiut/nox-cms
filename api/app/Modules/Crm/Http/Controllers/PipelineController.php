<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Modules\Crm\Http\Requests\StoreManualLeadRequest;
use App\Modules\Crm\Http\Resources\LeadActivityResource;
use App\Modules\Crm\Http\Resources\PipelineStageResource;
use App\Modules\Crm\Models\PipelineStage;
use App\Modules\Crm\Services\PipelineService;
use App\Modules\Lead\Http\Resources\LeadResource;
use App\Modules\Lead\Models\Lead;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Quadro kanban, movimentação de cartões e linha do tempo do lead. */
class PipelineController extends ApiController
{
    public function __construct(private readonly PipelineService $pipeline) {}

    public function board(Request $request): JsonResponse
    {
        return $this->success(PipelineStageResource::collection($this->pipeline->board($request->string('search'))));
    }

    public function storeLead(StoreManualLeadRequest $request): JsonResponse
    {
        return $this->created(LeadResource::make($this->pipeline->createLead($request->validated(), $request->user())), 'Lead criado.');
    }

    public function move(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate(['stage_id' => ['required', 'uuid']]);
        $stage = PipelineStage::query()->where('uuid', $data['stage_id'])->firstOrFail();

        return $this->success(LeadResource::make($this->pipeline->moveLead($lead, $stage, $request->user())));
    }

    public function activities(Lead $lead): JsonResponse
    {
        $activities = $lead->activities()->with('user')->latest('id')->limit(200)->get();

        return $this->success(LeadActivityResource::collection($activities));
    }

    public function note(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $activity = $this->pipeline->addNote($lead, $data['body'], $request->user());

        return $this->created(LeadActivityResource::make($activity->load('user')), 'Anotação registrada.');
    }
}
