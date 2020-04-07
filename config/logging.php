<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => 'single',

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/lumen-'.date('Y-m-d').'.log'),
            'level' => 'debug',
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lumen-'.date('Y-m-d').'.log'),
            'level' => 'debug',
            'days' => 7,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Lumen Log',
            'emoji' => ':boom:',
            'level' => 'critical',
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],

        /* monolog */

        'debug' => [
            'driver' => 'daily',
            'path' => storage_path('logs/monolog/debug/debug.log'),
            'level' => 'debug',
        ],

        'info' => [
            'driver' => 'daily',
            'path' => storage_path('logs/monolog/info/info.log'),
            'level' => 'info',
        ],

        'notice' => [
            'driver' => 'daily',
            'path' => storage_path('logs/monolog/notice/notice.log'),
            'level' => 'notice',
        ],

        'warning' => [
            'driver' => 'daily',
            'path' => storage_path('logs/monolog/warning/warning.log'),
            'level' => 'warning',
        ],

        'error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/monolog/error/error.log'),
            'level' => 'error',
        ],

        'critical' => [
            'driver' => 'daily',
            'path' => storage_path('logs/monolog/critical/critical.log'),
            'level' => 'critical',
        ],

        'alert' => [
            'driver' => 'daily',
            'path' => storage_path('logs/monolog/alert/alert.log'),
            'level' => 'alert',
        ],

        'emergency' => [
            'driver' => 'daily',
            'path' => storage_path('logs/monolog/emergency/emergency.log'),
            'level' => 'emergency',
        ],

        'test' => [
            'driver' => 'daily',
            'path' => storage_path('logs/monolog/test/test.log'),
            'level' => 'debug',
        ],
    ],

];