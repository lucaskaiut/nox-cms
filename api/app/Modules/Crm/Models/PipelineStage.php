<?php

namespace App\Modules\Crm\Models;

use App\Modules\Crm\Enums\StageOutcome;
use App\Modules\Lead\Models\Lead;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const COLORS = ['slate', 'blue', 'cyan', 'green', 'amber', 'orange', 'red', 'pink', 'violet'];

    protected $fillable = ['name', 'color', 'outcome', 'position'];

    protected function casts(): array
    {
        return ['outcome' => StageOutcome::class, 'position' => 'integer'];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'stage_id');
    }
}
