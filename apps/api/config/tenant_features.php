<?php

return [
    'basic' => [
        'dashboard' => true,
        'vehicles' => true,
        'gps_devices' => true,
        'services' => false,
        'registrations' => false,
        'users' => false,
        'reports' => false,
        'advanced_telemetry' => false,
    ],

    'pro' => [
        'dashboard' => true,
        'vehicles' => true,
        'gps_devices' => true,
        'services' => true,
        'registrations' => true,
        'users' => true,
        'reports' => false,
        'advanced_telemetry' => false,
    ],

    'enterprise' => [
        'dashboard' => true,
        'vehicles' => true,
        'gps_devices' => true,
        'services' => true,
        'registrations' => true,
        'users' => true,
        'reports' => true,
        'advanced_telemetry' => true,
    ],
];
