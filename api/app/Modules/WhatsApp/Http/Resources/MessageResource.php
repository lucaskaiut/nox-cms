<?php

namespace App\Modules\WhatsApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'direction' => $this->direction,
            'type' => $this->type,
            'body' => $this->body,
            'has_media' => $this->hasMedia(),
            'media_mime' => $this->media_mime,
            'media_filename' => $this->media_filename,
            'template_name' => $this->template_name,
            'status' => $this->status,
            'error' => $this->error,
            'user' => $this->user ? ['id' => $this->user->uuid, 'name' => $this->user->name] : null,
            'sent_at' => ($this->sent_at ?? $this->created_at)?->toIso8601String(),
        ];
    }
}
