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
            'status' => $this->status?->value, 'status_label' => $this->status?->label(),
            'gclid' => $this->gclid, 'gbraid' => $this->gbraid, 'wbraid' => $this->wbraid,
            'utm_source' => $this->utm_source, 'utm_medium' => $this->utm_medium, 'utm_campaign' => $this->utm_campaign,
            'utm_term' => $this->utm_term, 'utm_content' => $this->utm_content,
            'landing_path' => $this->landing_path, 'click_path' => $this->click_path, 'consent' => $this->consent,
            'clicked_at' => $this->clicked_at?->toIso8601String(), 'contacted_at' => $this->contacted_at?->toIso8601String(),
            'qualified_at' => $this->qualified_at?->toIso8601String(),
            'conversion_value' => $this->conversion_value, 'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
