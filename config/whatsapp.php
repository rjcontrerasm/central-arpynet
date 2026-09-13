<?php

$allowedWaIds = array_values(
    array_filter(
        array_map(
            static fn (string $value): string =>
                preg_replace(
                    '/\D+/',
                    '',
                    trim($value),
                ) ?? '',
            explode(
                ',',
                (string) env(
                    'WHATSAPP_ALLOWED_WA_IDS',
                    '',
                ),
            ),
        ),
    ),
);

$senderUserMap = [];

foreach (
    explode(
        ',',
        (string) env(
            'WHATSAPP_SENDER_USER_MAP',
            '',
        ),
    ) as $mapping
) {
    [$waId, $email] = array_pad(
        explode(':', $mapping, 2),
        2,
        '',
    );

    $waId = preg_replace(
        '/\D+/',
        '',
        trim($waId),
    ) ?? '';

    $email = strtolower(trim($email));

    if ($waId !== '' && $email !== '') {
        $senderUserMap[$waId] = $email;
    }
}

return [
    'enabled' => filter_var(
        env('WHATSAPP_ENABLED', false),
        FILTER_VALIDATE_BOOL,
    ),

    'verify_token' => env(
        'WHATSAPP_VERIFY_TOKEN',
    ),

    'app_secret' => env(
        'WHATSAPP_APP_SECRET',
    ),

    'allowed_wa_ids' => $allowedWaIds,

    'sender_user_map' => $senderUserMap,

    'user_email' => env(
        'WHATSAPP_USER_EMAIL',
        'rcontreras@arpynet.com',
    ),

    'default_organization_id' => env(
        'WHATSAPP_DEFAULT_ORGANIZATION_ID',
    ),

    'commands_enabled' => filter_var(
        env('WHATSAPP_COMMANDS_ENABLED', false),
        FILTER_VALIDATE_BOOL,
    ),

    'outbound_enabled' => filter_var(
        env('WHATSAPP_OUTBOUND_ENABLED', false),
        FILTER_VALIDATE_BOOL,
    ),

    'graph_version' => env(
        'WHATSAPP_GRAPH_VERSION',
        'v26.0',
    ),

    'access_token' => env(
        'WHATSAPP_ACCESS_TOKEN',
    ),

    'phone_number_id' => env(
        'WHATSAPP_PHONE_NUMBER_ID',
    ),

    'confirm_task_creation' => filter_var(
        env('WHATSAPP_CONFIRM_TASK_CREATION', true),
        FILTER_VALIDATE_BOOL,
    ),

    'confirmation_prefix' => env(
        'WHATSAPP_CONFIRMATION_PREFIX',
        '✅ Tarea registrada:',
    ),
];
