<?php

return [
    // Exportação de conversões offline (Google Ads > Conversões > Uploads).
    'google_ads' => [
        'conversion_name' => env('LEADS_GOOGLE_ADS_CONVERSION_NAME', 'CRM | Lead qualificado (offline)'),
        'currency' => env('LEADS_GOOGLE_ADS_CURRENCY', 'BRL'),
        'timezone' => env('LEADS_GOOGLE_ADS_TIMEZONE', 'America/Sao_Paulo'),
        // O Google recusa cliques com mais de 90 dias.
        'click_window_days' => 90,
    ],
];
