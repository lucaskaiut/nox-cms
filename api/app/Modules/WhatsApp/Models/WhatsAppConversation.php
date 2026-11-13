<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\Lead\Models\Lead;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    use BelongsToTenant;
    use HasUuid;

    /** Janela de atendimento da Meta: texto livre só até 24h após a última mensagem do contato. */
    public const SERVICE_WINDOW_HOURS = 24;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'tenant_id', 'wa_id', 'contact_name', 'lead_id', 'last_message_preview',
        'last_message_at', 'last_inbound_at', 'unread_count',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id');
    }

    public function windowOpen(): bool
    {
        return $this->last_inbound_at !== null
            && $this->last_inbound_at->gt(now()->subHours(self::SERVICE_WINDOW_HOURS));
    }

    public function windowExpiresAt(): ?\Illuminate\Support\Carbon
    {
        return $this->last_inbound_at?->copy()->addHours(self::SERVICE_WINDOW_HOURS);
    }
}
