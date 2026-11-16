<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'planner_ai' => [
        'key' => env('PLANNER_AI_API_KEY'),
        'model' => env('PLANNER_AI_MODEL', 'gpt-4.1-mini'),
        'research_model' => env('PLANNER_AI_RESEARCH_MODEL', 'gpt-6-astra'),
        'image_model' => env('PLANNER_AI_IMAGE_MODEL', 'gpt-image-2.5-flare'),
        'daily_brand_limit' => env('PLANNER_AI_DAILY_BRAND_LIMIT', 20),
        // Optional operator-supplied reference prices (microUSD per 1m tokens); never inferred from invoices.
        'cost_rates_json' => env('PLANNER_AI_COST_RATES_JSON', ''),
        'cost_rate_revision' => env('PLANNER_AI_COST_RATE_REVISION', ''),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
