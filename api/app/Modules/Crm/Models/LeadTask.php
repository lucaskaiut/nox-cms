<?php

namespace App\Modules\Crm\Models;

use App\Modules\Lead\Models\Lead;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Próximo passo combinado com o lead (ligar, enviar proposta…), com prazo. */
class LeadTask extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = ['tenant_id', 'lead_id', 'assigned_to', 'created_by', 'title', 'notes', 'due_at', 'completed_at', 'reminded_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime', 'reminded_at' => 'datetime'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
