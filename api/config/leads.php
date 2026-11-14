<?php

return [
    // Endereço do painel (links em e-mails e alertas).
    'panel_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost')),

    // Exportação de conversões offline (Google Ads > Conversões > Uploads).
    'google_ads' => [
        'conversion_name' => env('LEADS_GOOGLE_ADS_CONVERSION_NAME', 'CRM | Lead qualificado (offline)'),
        'currency' => env('LEADS_GOOGLE_ADS_CURRENCY', 'BRL'),
        'timezone' => env('LEADS_GOOGLE_ADS_TIMEZONE', 'America/Sao_Paulo'),
        // O Google recusa cliques com mais de 90 dias.
        'click_window_days' => 90,
    ],

    // Google Ads API (envio automático de conversões offline).
    'google_ads_api' => [
        'version' => env('GOOGLE_ADS_API_VERSION', 'v26'),
        'base_url' => env('GOOGLE_ADS_API_BASE_URL', 'https://googleads.googleapis.com'),
        'batch_size' => 200,
    ],
];
