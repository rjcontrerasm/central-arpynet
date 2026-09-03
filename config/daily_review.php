<?php

return [
    'reminder' => [
        'enabled' => (bool) env(
            'CENTRAL_DAILY_REVIEW_REMINDER_ENABLED',
            true,
        ),
        'hour' => (int) env(
            'CENTRAL_DAILY_REVIEW_REMINDER_HOUR',
            17,
        ),
    ],
];
