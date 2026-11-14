<?php

namespace App\Modules\Analytics\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Investimento em mídia num período, para calcular custo por lead/venda. */
class AdSpend extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const CHANNELS = ['google_ads' => 'Google Ads', 'meta_ads' => 'Meta Ads', 'linkedin_ads' => 'LinkedIn Ads', 'other' => 'Outro'];

    protected $fillable = ['channel', 'period_start', 'period_end', 'amount', 'notes'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'amount' => 'decimal:2'];
    }
}
