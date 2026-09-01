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
];
