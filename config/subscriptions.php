<?php

return [
    'plans' => [
        'trial' => [
            'name' => '14-Day Trial',
            'price' => 0,
            'currency' => 'KES',
            'interval' => 'once',
            'student_limit' => 100,
            'features' => [
                'basic_attendance',
                'basic_grades',
                '5_staff_accounts',
            ],
        ],
        'basic' => [
            'name' => 'Basic',
            'price' => 2999,
            'currency' => 'KES',
            'interval' => 'monthly',
            'student_limit' => 500,
            'features' => [
                'full_attendance',
                'grades',
                'reports',
                'unlimited_staff',
            ],
        ],
        'premium' => [
            'name' => 'Premium',
            'price' => 7999,
            'currency' => 'KES',
            'interval' => 'monthly',
            'student_limit' => 2000,
            'features' => [
                'attendance',
                'grades',
                'reports',
                'sms_notifications',
                'parent_portal',
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'price' => 19999,
            'currency' => 'KES',
            'interval' => 'monthly',
            'student_limit' => null, // unlimited
            'features' => [
                'everything',
                'custom_reports',
                'api_access',
                'priority_support',
            ],
        ],
    ],

    'stripe' => [
        'model' => App\Models\School::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => 300,
        ],
    ],

    'mpesa' => [
        'consumer_key' => env('M-PESA_CONSUMER_KEY'),
        'consumer_secret' => env('M-PESA_CONSUMER_SECRET'),
        'passkey' => env('M-PESA_PASSKEY'),
        'shortcode' => env('M-PESA_SHORTCODE'),
        'callback_url' => env('M-PESA_CALLBACK_URL'),
    ],
];
