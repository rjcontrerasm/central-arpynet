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
