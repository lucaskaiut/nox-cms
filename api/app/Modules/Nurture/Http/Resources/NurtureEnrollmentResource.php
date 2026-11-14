<?php

namespace App\Modules\Nurture\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NurtureEnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status,
            'stopped_reason' => $this->stopped_reason,
            'next_step' => $this->next_step,
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'last_sent_at' => $this->last_sent_at?->toIso8601String(),
            'sequence' => $this->whenLoaded('sequence', fn () => $this->sequence ? [
                'id' => $this->sequence->uuid, 'name' => $this->sequence->name,
                'steps_count' => $this->sequence->relationLoaded('steps') ? $this->sequence->steps->count() : null,
            ] : null),
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? ['id' => $this->lead->uuid, 'ref' => $this->lead->ref, 'name' => $this->lead->name, 'email' => $this->lead->email] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
