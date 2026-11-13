<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Credenciais da WhatsApp Cloud API do tenant, preenchidas à mão no painel
 * (sem Embedded Signup). Token e app secret ficam criptografados.
 */
class WhatsAppAccount extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'tenant_id', 'phone_number_id', 'waba_id', 'display_phone', 'verified_name', 'access_token', 'app_secret',
        'verify_token', 'graph_version', 'is_active', 'verified_at', 'last_webhook_at',
    ];

    protected $hidden = ['access_token', 'app_secret'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'app_secret' => 'encrypted',
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
            'last_webhook_at' => 'datetime',
        ];
    }

    public function isConfigured(): bool
    {
        return filled($this->phone_number_id) && filled($this->waba_id) && filled($this->access_token);
    }
}
