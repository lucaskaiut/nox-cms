<?php

namespace App\Modules\Nurture\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NurtureSequenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'trigger' => $this->trigger,
            'trigger_tag' => $this->whenLoaded('triggerTag', fn () => $this->triggerTag ? ['id' => $this->triggerTag->uuid, 'name' => $this->triggerTag->name] : null),
            'trigger_delay_days' => $this->trigger_delay_days,
            'is_active' => $this->is_active,
            'steps_count' => $this->when(isset($this->steps_count), fn () => (int) $this->steps_count),
            'active_count' => $this->when(isset($this->active_count), fn () => (int) $this->active_count),
            'completed_count' => $this->when(isset($this->completed_count), fn () => (int) $this->completed_count),
            'steps' => $this->whenLoaded('steps', fn () => $this->steps->map(fn ($s) => [
                'id' => $s->uuid, 'position' => $s->position, 'delay_days' => $s->delay_days,
                'subject' => $s->subject, 'body' => $s->body,
                'post' => $s->relationLoaded('post') && $s->post ? ['id' => $s->post->uuid, 'title' => $s->post->title] : null,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
