<?php

namespace App\Modules\Lead\Models;

use App\Modules\Crm\Models\LeadActivity;
use App\Modules\Crm\Models\PipelineStage;
use App\Modules\Crm\Models\LeadTag;
use App\Modules\Crm\Models\LeadTask;
use App\Modules\GoogleAds\Models\LeadConversionUpload;
use App\Modules\Lead\Enums\LeadStatus;
use App\Modules\Nurture\Models\NurtureEnrollment;
use App\Modules\Post\Models\Post;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'post_id', 'cta', 'lost_reason', 'lost_at', 'won_at', 'email_opt_out_at',
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
            'lost_at' => 'datetime',
            'won_at' => 'datetime',
            'email_opt_out_at' => 'datetime',
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

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(LeadTag::class, 'lead_tag');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(LeadTask::class);
    }

    public function conversionUploads(): HasMany
    {
        return $this->hasMany(LeadConversionUpload::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(NurtureEnrollment::class);
    }

    /** Origem resumida para relatórios: anúncio, blog, WhatsApp, formulário… */
    public function source(): string
    {
        if ($this->gclid || $this->gbraid || $this->wbraid || in_array(strtolower((string) $this->utm_medium), ['cpc', 'ppc', 'paid'], true)) {
            return 'paid';
        }
        if ($this->post_id) {
            return 'blog';
        }
        if ($this->utm_source) {
            return 'referral';
        }

        return match ($this->channel) {
            'manual' => 'manual',
            'whatsapp' => 'whatsapp',
            default => 'direct',
        };
    }

    public function canReceiveEmail(): bool
    {
        return filled($this->email) && $this->email_opt_out_at === null && $this->status !== LeadStatus::SPAM;
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(WhatsAppConversation::class)->latestOfMany();
    }
}
