<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Square Application ID
    |--------------------------------------------------------------------------
    */
    'application_id' => env('SQUARE_APPLICATION_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Square Access Token
    |--------------------------------------------------------------------------
    */
    'access_token' => env('SQUARE_ACCESS_TOKEN', env('SQUARE_TOKEN', '')),

    /*
    |--------------------------------------------------------------------------
    | Square Location ID
    |--------------------------------------------------------------------------
    */
    'location_id' => env('SQUARE_LOCATION_ID', env('SQUARE_LOCATION', '')),

    /*
    |--------------------------------------------------------------------------
    | Square Environment
    |--------------------------------------------------------------------------
    | Use 'sandbox' for testing, 'production' for live payments.
    */
    'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Square Webhook Signature Key
    |--------------------------------------------------------------------------
    | Signature key from the Square Developer portal for the webhook subscription.
    | Required to verify that webhook payloads originate from Square.
    */
    'webhook_signature_key' => env('SQUARE_WEBHOOK_SIGNATURE_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Square Webhook Notification URL
    |--------------------------------------------------------------------------
    | The exact notification URL as registered in the Square Developer portal.
    | Must match the URL Square uses to send webhooks (used in signature verification).
    */
    'webhook_notification_url' => env('SQUARE_WEBHOOK_NOTIFICATION_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Square Webhook Skip Verification (testing only)
    |--------------------------------------------------------------------------
    | When true and APP_ENV=testing, signature verification is skipped.
    | Never enable in production.
    */
    'webhook_skip_verification' => env('SQUARE_WEBHOOK_SKIP_VERIFICATION', false),

];
