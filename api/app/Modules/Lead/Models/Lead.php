<?php

namespace App\Modules\Lead\Models;

use App\Modules\Crm\Models\LeadActivity;
use App\Modules\Crm\Models\PipelineStage;
use App\Modules\Lead\Enums\LeadStatus;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Contato iniciado no site. WhatsApp: só cliques pagos; o `ref` (ex.: NX-7K4P2Q)
 * vai no texto da mensagem e liga a conversa ao GCLID — o registro nasce no
 * clique do botão, antes de a pessoa enviar a mensagem. Formulário: qualquer
 * visitante, com nome/contato/mensagem e o click ID quando houver.
 */
class Lead extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'ref', 'channel', 'name', 'email', 'phone', 'company', 'message', 'status', 'gclid', 'gbraid', 'wbraid',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'landing_path', 'click_path', 'consent', 'clicked_at', 'contacted_at',
        'qualified_at', 'conversion_value', 'notes', 'stage_id', 'stage_changed_at', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'clicked_at' => 'datetime',
            'contacted_at' => 'datetime',
            'qualified_at' => 'datetime',
            'conversion_value' => 'decimal:2',
            'stage_changed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(WhatsAppConversation::class)->latestOfMany();
    }
}
