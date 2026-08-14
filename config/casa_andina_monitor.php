<?php

return [
    'enabled' => filter_var(
        env('CASA_ANDINA_MONITOR_ENABLED', false),
        FILTER_VALIDATE_BOOL,
    ),

    'base_url' => env('CASA_ANDINA_MONITOR_URL'),

    'endpoint' => env(
        'CASA_ANDINA_MONITOR_ENDPOINT',
        '/api/integrations/central/status',
    ),

    'token' => env('CASA_ANDINA_MONITOR_TOKEN'),

    'organization_slug' => env(
        'CASA_ANDINA_MONITOR_ORGANIZATION_SLUG',
        'casa-andina',
    ),

    'owner_email' => env(
        'CENTRAL_OWNER_EMAIL',
        'rcontreras@arpynet.com',
    ),

    'timeout' => (int) env(
        'CASA_ANDINA_MONITOR_TIMEOUT',
        10,
    ),
];
