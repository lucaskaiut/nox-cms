<?php

namespace App\Modules\Crm\Models;

use App\Modules\Lead\Models\Lead;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha do tempo do lead: criação, anotações, mudanças de etapa e eventos do
 * WhatsApp (primeira mensagem, conversa vinculada).
 */
class LeadActivity extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const CREATED = 'created';
    public const NOTE = 'note';
    public const STAGE_CHANGED = 'stage_changed';
    public const WHATSAPP_LINKED = 'whatsapp_linked';

    protected $fillable = ['lead_id', 'user_id', 'type', 'body', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
