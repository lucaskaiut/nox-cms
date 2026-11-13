<?php

namespace App\Modules\WhatsApp\Http\Resources;

use App\Modules\WhatsApp\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lead = $this->lead;

        return [
            'id' => $this->uuid,
            'wa_id' => $this->wa_id,
            'phone' => PhoneNumber::format($this->wa_id),
            'contact_name' => $this->contact_name,
            'last_message_preview' => $this->last_message_preview,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_inbound_at' => $this->last_inbound_at?->toIso8601String(),
            'unread_count' => $this->unread_count,
            'window_open' => $this->windowOpen(),
            'window_expires_at' => $this->windowExpiresAt()?->toIso8601String(),
            'lead' => $lead ? [
                'id' => $lead->uuid, 'ref' => $lead->ref, 'name' => $lead->name, 'company' => $lead->company,
                'channel' => $lead->channel, 'status' => $lead->status?->value,
                'stage' => $lead->stage ? [
                    'id' => $lead->stage->uuid, 'name' => $lead->stage->name,
                    'color' => $lead->stage->color, 'outcome' => $lead->stage->outcome->value,
                ] : null,
                'has_click_id' => (bool) ($lead->gclid || $lead->gbraid || $lead->wbraid),
            ] : null,
        ];
    }
}
