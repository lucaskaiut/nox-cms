<?php

namespace App\Modules\GoogleAds\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Credenciais da Google Ads API do tenant. Segredos ficam criptografados. */
class GoogleAdsAccount extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'customer_id', 'login_customer_id', 'developer_token', 'client_id', 'client_secret',
        'refresh_token', 'qualified_action_id', 'won_action_id', 'currency', 'is_active', 'last_upload_at', 'last_error',
    ];

    protected $hidden = ['developer_token', 'client_secret', 'refresh_token'];

    protected $attributes = ['currency' => 'BRL', 'is_active' => false];

    protected function casts(): array
    {
        return [
            'developer_token' => 'encrypted',
            'client_secret' => 'encrypted',
            'refresh_token' => 'encrypted',
            'is_active' => 'boolean',
            'last_upload_at' => 'datetime',
        ];
    }

    public function isConfigured(): bool
    {
        return filled($this->customer_id) && filled($this->developer_token) && filled($this->client_id)
            && filled($this->client_secret) && filled($this->refresh_token)
            && (filled($this->qualified_action_id) || filled($this->won_action_id));
    }
}
