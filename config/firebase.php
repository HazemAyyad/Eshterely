<?php

$credentialsRaw = trim((string) env('FIREBASE_CREDENTIALS', ''));
$credentialsPath = '';
if ($credentialsRaw !== '') {
    $isAbsolute = $credentialsRaw[0] === '/'
        || (strlen($credentialsRaw) > 1 && preg_match('#^[A-Za-z]:[/\\\\]#', $credentialsRaw));
    $credentialsPath = $isAbsolute ? $credentialsRaw : base_path($credentialsRaw);
}

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase credentials
    |--------------------------------------------------------------------------
    | Path to service account JSON file (env value, may be relative to project root).
    | Use FIREBASE_CREDENTIALS in .env. Relative paths are resolved via base_path().
    */
    'credentials' => $credentialsRaw,

    /*
    |--------------------------------------------------------------------------
    | Resolved credentials path
    |--------------------------------------------------------------------------
    | Absolute path used to load the service account file. Check file_exists() and
    | is_readable() before creating the Firebase client.
    */
    'credentials_path' => $credentialsPath,

    /*
    |--------------------------------------------------------------------------
    | FCM / Cloud Messaging
    |--------------------------------------------------------------------------
    */
    'fcm' => [
        'enabled' => $credentialsPath !== '',
    ],
];
