<?php

namespace App\Modules\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type' => $this->type,
            'body' => $this->body,
            'meta' => $this->meta,
            'user' => $this->user ? ['id' => $this->user->uuid, 'name' => $this->user->name] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
