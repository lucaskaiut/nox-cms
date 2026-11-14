<?php

namespace App\Modules\SearchConsole\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Conta de serviço com acesso de leitura à propriedade do Search Console. */
class SearchConsoleAccount extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'site_url', 'service_account_json', 'service_account_email', 'is_active', 'last_sync_at', 'last_error'];

    protected $hidden = ['service_account_json'];

    protected $attributes = ['is_active' => false];

    protected function casts(): array
    {
        return ['service_account_json' => 'encrypted', 'is_active' => 'boolean', 'last_sync_at' => 'datetime'];
    }

    public function isConfigured(): bool
    {
        return filled($this->site_url) && filled($this->service_account_json);
    }
}
