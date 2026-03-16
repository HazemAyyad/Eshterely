<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase credentials
    |--------------------------------------------------------------------------
    | Path to service account JSON file, or empty to disable FCM sending.
    */
    'credentials' => env('FIREBASE_CREDENTIALS', ''),

    /*
    |--------------------------------------------------------------------------
    | FCM / Cloud Messaging
    |--------------------------------------------------------------------------
    */
    'fcm' => [
        'enabled' => ! empty(env('FIREBASE_CREDENTIALS')),
    ],
];
