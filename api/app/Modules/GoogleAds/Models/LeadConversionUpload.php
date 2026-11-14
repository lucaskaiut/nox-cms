<?php

namespace App\Modules\GoogleAds\Models;

use App\Modules\Lead\Models\Lead;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma conversão offline (qualificado/ganho) a enviar ou já enviada ao Google Ads. */
class LeadConversionUpload extends Model
{
    use BelongsToTenant;

    public const QUALIFIED = 'qualified';
    public const WON = 'won';

    public const PENDING = 'pending';
    public const UPLOADED = 'uploaded';
    public const FAILED = 'failed';

    /** Tentativas (uma por hora) antes de desistir: cliques recentes podem levar horas para o Google aceitar. */
    public const MAX_ATTEMPTS = 24;

    protected $fillable = ['tenant_id', 'lead_id', 'kind', 'status', 'conversion_at', 'value', 'attempts', 'error', 'uploaded_at'];

    protected function casts(): array
    {
        return ['conversion_at' => 'datetime', 'uploaded_at' => 'datetime', 'value' => 'decimal:2', 'attempts' => 'integer'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
