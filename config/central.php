<?php

return [
    'summary' => [
        'daily_time' => env(
            'CENTRAL_SUMMARY_DAILY_TIME',
            '07:30',
        ),
        'weekly_day' => (int) env(
            'CENTRAL_SUMMARY_WEEKLY_DAY',
            1,
        ),
        'weekly_time' => env(
            'CENTRAL_SUMMARY_WEEKLY_TIME',
            '07:35',
        ),
    ],
    'summary_whatsapp' => [
        'enabled' => filter_var(
            env(
                'CENTRAL_SUMMARY_WHATSAPP_ENABLED',
                false,
            ),
            FILTER_VALIDATE_BOOL,
        ),
        'user_email' => env(
            'CENTRAL_SUMMARY_WHATSAPP_USER_EMAIL',
            'rcontreras@arpynet.com',
        ),
        'template' => env(
            'CENTRAL_SUMMARY_WHATSAPP_TEMPLATE',
            'central_executive_summary',
        ),
        'language' => env(
            'CENTRAL_SUMMARY_WHATSAPP_LANGUAGE',
            'es_PE',
        ),
        'daily_time' => env(
            'CENTRAL_SUMMARY_WHATSAPP_DAILY_TIME',
            '07:32',
        ),
        'weekly_time' => env(
            'CENTRAL_SUMMARY_WHATSAPP_WEEKLY_TIME',
            '07:37',
        ),
    ],

    'critical_whatsapp' => [
        'enabled' => filter_var(
            env('CENTRAL_CRITICAL_WHATSAPP_ENABLED', false),
            FILTER_VALIDATE_BOOL,
        ),
        'user_email' => env(
            'CENTRAL_CRITICAL_WHATSAPP_USER_EMAIL',
            'rcontreras@arpynet.com',
        ),
        'template' => env(
            'CENTRAL_CRITICAL_WHATSAPP_TEMPLATE',
            'central_critical_alert',
        ),
        'language' => env(
            'CENTRAL_CRITICAL_WHATSAPP_LANGUAGE',
            'es_PE',
        ),
        'cooldown_minutes' => (int) env(
            'CENTRAL_CRITICAL_WHATSAPP_COOLDOWN_MINUTES',
            360,
        ),
        'retry_minutes' => (int) env(
            'CENTRAL_CRITICAL_WHATSAPP_RETRY_MINUTES',
            60,
        ),
        'max_items' => (int) env(
            'CENTRAL_CRITICAL_WHATSAPP_MAX_ITEMS',
            5,
        ),
    ],

    'summary_mail' => [
        'enabled' => (bool) env(
            'CENTRAL_SUMMARY_MAIL_ENABLED',
            true,
        ),
        'mailer' => env(
            'CENTRAL_SUMMARY_MAILER',
            'sendmail',
        ),
        'from_address' => env(
            'CENTRAL_SUMMARY_MAIL_FROM',
            'notificaciones@central.arpynet.com',
        ),
        'from_name' => env(
            'CENTRAL_SUMMARY_MAIL_FROM_NAME',
            'Central ARPYNET',
        ),
    ],

];
