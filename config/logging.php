<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'worker'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'warning'),
        ],

        'worker' => [
            'driver' => 'single',
            'path' => storage_path('logs/worker.log'),
            'level' => env('LOG_LEVEL', 'info'),
        ],

        'schedule' => [
            'driver' => 'single',
            'path' => storage_path('logs/schedule.log'),
            'level' => env('LOG_LEVEL', 'info'),
        ],

        'security' => [
            'driver' => 'single',
            'path' => storage_path('logs/security.log'),
            'level' => env('LOG_LEVEL', 'info'),
        ],

        'billing' => [
            'driver' => 'single',
            'path' => storage_path('logs/billing.log'),
            'level' => env('LOG_LEVEL', 'info'),
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'warning'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'warning'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],
    ],
];
