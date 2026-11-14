<?php

namespace App\Modules\Nurture\Models;

use App\Modules\Lead\Models\Lead;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NurtureEnrollment extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const ACTIVE = 'active';
    public const COMPLETED = 'completed';
    public const STOPPED = 'stopped';

    protected $fillable = ['tenant_id', 'sequence_id', 'lead_id', 'next_step', 'next_run_at', 'status', 'stopped_reason', 'last_sent_at'];

    protected function casts(): array
    {
        return ['next_run_at' => 'datetime', 'last_sent_at' => 'datetime', 'next_step' => 'integer'];
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(NurtureSequence::class, 'sequence_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
