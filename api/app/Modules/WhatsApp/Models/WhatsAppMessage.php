<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const IN = 'in';
    public const OUT = 'out';

    /** Ordem dos status de entrega: um webhook atrasado nunca rebaixa o status. */
    public const STATUS_RANK = ['pending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'tenant_id', 'conversation_id', 'wamid', 'direction', 'type', 'body', 'media_id', 'media_mime',
        'media_filename', 'template_name', 'status', 'error', 'user_id', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasMedia(): bool
    {
        return filled($this->media_id);
    }
}
