<?php

namespace App\Modules\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'notes' => $this->notes,
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'overdue' => $this->completed_at === null && $this->due_at?->isPast(),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? ['id' => $this->assignee->uuid, 'name' => $this->assignee->name] : null),
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? [
                'id' => $this->lead->uuid, 'ref' => $this->lead->ref, 'name' => $this->lead->name,
                'company' => $this->lead->company, 'phone' => $this->lead->phone,
                'stage' => $this->lead->relationLoaded('stage') && $this->lead->stage
                    ? ['name' => $this->lead->stage->name, 'color' => $this->lead->stage->color] : null,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
