<?php

namespace App\Modules\Crm\Http\Resources;

use App\Modules\Lead\Http\Resources\LeadResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PipelineStageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'color' => $this->color,
            'outcome' => $this->outcome->value,
            'outcome_label' => $this->outcome->label(),
            'position' => $this->position,
            'leads_count' => $this->whenCounted('leads'),
            'total' => $this->when($this->getAttribute('total') !== null, fn () => $this->getAttribute('total')),
            'leads' => LeadResource::collection($this->whenLoaded('leads')),
        ];
    }
}
