<?php

use App\Modules\Tenant\Providers\TenantServiceProvider;
use App\Modules\Webhook\Providers\WebhookServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenantServiceProvider::class,
    WebhookServiceProvider::class,
];
