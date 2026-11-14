<?php

namespace App\Modules\Crm\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Configurações comerciais do tenant (uma linha por tenant). */
class CrmSettings extends Model
{
    use BelongsToTenant;

    public const DEFAULT_LOST_REASONS = [
        'Preço', 'Sem resposta', 'Escolheu concorrente', 'Sem orçamento agora', 'Fora do perfil', 'Desistiu do projeto',
    ];

    protected $table = 'crm_settings';

    protected $fillable = [
        'tenant_id', 'site_url', 'post_url_pattern', 'lost_reasons', 'stale_lead_days', 'stale_post_months',
        'notify_emails', 'notify_new_lead', 'notify_daily_digest', 'telegram_bot_token', 'telegram_chat_id',
        'auto_reply_enabled', 'auto_reply_subject', 'auto_reply_body', 'email_signature',
    ];

    protected $hidden = ['telegram_bot_token'];

    protected $attributes = [
        'stale_lead_days' => 3,
        'stale_post_months' => 6,
        'notify_new_lead' => true,
        'notify_daily_digest' => true,
        'auto_reply_enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'lost_reasons' => 'array',
            'notify_emails' => 'array',
            'notify_new_lead' => 'boolean',
            'notify_daily_digest' => 'boolean',
            'auto_reply_enabled' => 'boolean',
            'telegram_bot_token' => 'encrypted',
            'stale_lead_days' => 'integer',
            'stale_post_months' => 'integer',
        ];
    }

    /** Configuração do tenant atual (ou do informado), criada sob demanda. */
    public static function current(?int $tenantId = null): self
    {
        $query = $tenantId ? static::query()->forTenant($tenantId) : static::query();

        return $query->first() ?? new static(['tenant_id' => $tenantId ?? \App\Modules\Tenant\Support\Facades\TenantContext::tenantId()]);
    }

    /** @return list<string> */
    public function lostReasons(): array
    {
        return $this->lost_reasons ?: self::DEFAULT_LOST_REASONS;
    }

    /** URL pública de um post, a partir do endereço do site e do padrão. */
    public function postUrl(string $slug): ?string
    {
        if (blank($this->site_url)) {
            return null;
        }

        return rtrim($this->site_url, '/').str_replace('{slug}', $slug, $this->post_url_pattern ?: '/blog/{slug}');
    }

    /** Extrai o slug de um caminho do site conforme o padrão (ou o último segmento). */
    public function slugFromPath(?string $path): ?string
    {
        $path = parse_url((string) $path, PHP_URL_PATH);
        if (blank($path) || $path === '/') {
            return null;
        }

        if (filled($this->post_url_pattern) && str_contains($this->post_url_pattern, '{slug}')) {
            $regex = '#^'.str_replace('\{slug\}', '([a-z0-9]+(?:-[a-z0-9]+)*)', preg_quote(rtrim($this->post_url_pattern, '/'), '#')).'/?$#';
            if (preg_match($regex, $path, $m)) {
                return $m[1];
            }

            return null;
        }

        $slug = basename(rtrim($path, '/'));

        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) ? $slug : null;
    }
}
