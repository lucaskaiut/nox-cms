<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Modules\Crm\Http\Resources\LeadTagResource;
use App\Modules\Crm\Models\LeadTag;
use App\Modules\Crm\Models\PipelineStage;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadTagController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->success(LeadTagResource::collection(LeadTag::query()->withCount('leads')->orderBy('name')->get()));
    }

    public function update(Request $request, LeadTag $tag): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:60', Rule::unique('lead_tags', 'name')->where('tenant_id', TenantContext::tenantId())->ignore($tag->id)],
            'color' => ['sometimes', 'string', Rule::in(PipelineStage::COLORS)],
        ]);
        $tag->fill($data)->save();

        return $this->success(LeadTagResource::make($tag->loadCount('leads')), 'Tag atualizada.');
    }

    public function destroy(LeadTag $tag): JsonResponse
    {
        $tag->delete();

        return $this->success(null, 'Tag removida.');
    }
}
