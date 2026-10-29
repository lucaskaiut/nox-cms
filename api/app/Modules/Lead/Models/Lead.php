<?php

namespace App\Modules\Lead\Models;

use App\Modules\Lead\Enums\LeadStatus;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Contato iniciado no site a partir de um clique pago. O `ref` (ex.: NX-7K4P2Q)
 * vai no texto da mensagem do WhatsApp e liga a conversa ao GCLID.
 */
class Lead extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'ref', 'channel', 'status', 'gclid', 'gbraid', 'wbraid',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'landing_path', 'click_path', 'consent', 'clicked_at', 'contacted_at',
        'qualified_at', 'conversion_value', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'clicked_at' => 'datetime',
            'contacted_at' => 'datetime',
            'qualified_at' => 'datetime',
            'conversion_value' => 'decimal:2',
        ];
    }
}
