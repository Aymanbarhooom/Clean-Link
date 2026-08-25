<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],
    'google' => [
        'routes_api_key' => env('GOOGLE_ROUTES_API_KEY'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET')
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'models' => (static function (): array {
            $defaults = [
                'gemini-3.6-flash',
                'gemini-3.7-flash',
                'gemini-3.5-flash',
                'gemini-3.5-flash-lite',
                'gemini-3.1-flash-lite',
                'gemini-2.5-flash-lite',
                'gemini-3-flash-preview',
                'gemini-2.5-flash'
            ];
            $configured = trim((string) env('GEMINI_MODELS', ''));

            if ($configured === '') {
                $legacyModel = trim((string) env('GEMINI_MODEL', ''));
                $configured = implode(',', array_filter([
                    $legacyModel,
                    ...$defaults,
                ]));
            }

            return array_values(array_unique(array_filter(array_map(
                static fn (string $model): string => trim($model),
                explode(',', $configured)
            ))));
        })(),
        'base_url' => env(
            'GEMINI_BASE_URL',
            'https://generativelanguage.googleapis.com/v1beta'
        ),
        'retry_attempts' => (int) env('GEMINI_RETRY_ATTEMPTS', 2),
        'retry_delay_ms' => (int) env('GEMINI_RETRY_DELAY_MS', 250),
    ],


];
