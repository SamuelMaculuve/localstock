<?php

return [
    'table_name' => 'authentication_log',
    'db_connection' => null,

    'events' => [
        'login' => \Illuminate\Auth\Events\Login::class,
        'failed' => \Illuminate\Auth\Events\Failed::class,
        'logout' => \Illuminate\Auth\Events\Logout::class,
        'logout-other-devices' => \Illuminate\Auth\Events\OtherDeviceLogout::class,
    ],

    'notifications' => [
        'new-device' => [
            'enabled' => env('NEW_DEVICE_NOTIFICATION', true),
            'location' => false, // Disable to avoid GeoIP/cache errors on login
            'template' => \Rappasoft\LaravelAuthenticationLog\Notifications\NewDevice::class,
        ],
        'failed-login' => [
            'enabled' => env('FAILED_LOGIN_NOTIFICATION', false),
            'location' => false, // Disable to avoid GeoIP/cache errors on admin failed login
            'template' => \Rappasoft\LaravelAuthenticationLog\Notifications\FailedLogin::class,
        ],
    ],

    'purge' => 365,
];
