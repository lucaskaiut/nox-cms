<?php

namespace App\Modules\Nurture\Models;

use App\Modules\Crm\Models\LeadTag;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NurtureSequence extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_TAG = 'tag';
    public const TRIGGER_LOST = 'lost';

    protected $fillable = ['name', 'trigger', 'trigger_tag_id', 'trigger_delay_days', 'is_active'];

    protected $attributes = ['trigger' => self::TRIGGER_MANUAL, 'trigger_delay_days' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'trigger_delay_days' => 'integer'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(NurtureStep::class, 'sequence_id')->orderBy('position')->orderBy('id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(NurtureEnrollment::class, 'sequence_id');
    }

    public function triggerTag(): BelongsTo
    {
        return $this->belongsTo(LeadTag::class, 'trigger_tag_id');
    }
}
