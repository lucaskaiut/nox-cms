<?php

namespace App\Modules\Lead\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid, 'ref' => $this->ref, 'channel' => $this->channel,
            'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone,
            'company' => $this->company, 'message' => $this->message,
            'status' => $this->status?->value, 'status_label' => $this->status?->label(),
            'gclid' => $this->gclid, 'gbraid' => $this->gbraid, 'wbraid' => $this->wbraid,
            'utm_source' => $this->utm_source, 'utm_medium' => $this->utm_medium, 'utm_campaign' => $this->utm_campaign,
            'utm_term' => $this->utm_term, 'utm_content' => $this->utm_content,
            'landing_path' => $this->landing_path, 'click_path' => $this->click_path, 'consent' => $this->consent,
            'clicked_at' => $this->clicked_at?->toIso8601String(), 'contacted_at' => $this->contacted_at?->toIso8601String(),
            'qualified_at' => $this->qualified_at?->toIso8601String(),
            'conversion_value' => $this->conversion_value, 'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'stage' => $this->whenLoaded('stage', fn () => $this->stage ? [
                'id' => $this->stage->uuid, 'name' => $this->stage->name,
                'color' => $this->stage->color, 'outcome' => $this->stage->outcome->value,
            ] : null),
            'stage_changed_at' => $this->stage_changed_at?->toIso8601String(),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'conversation' => $this->whenLoaded('conversation', fn () => $this->conversation ? [
                'id' => $this->conversation->uuid,
                'unread_count' => $this->conversation->unread_count,
                'last_inbound_at' => $this->conversation->last_inbound_at?->toIso8601String(),
                'last_message_at' => $this->conversation->last_message_at?->toIso8601String(),
            ] : null),
        ];
    }
}
