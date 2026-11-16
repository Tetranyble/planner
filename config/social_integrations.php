<?php

return [
    // Update API versions after checking each provider's release/deprecation policy.
    'meta_deletion_max_age_seconds' => 604800,
    // Never perform external grant revocation until runtime/provider validation has passed.
    'remote_revocation_enabled' => env('SOCIAL_REMOTE_REVOCATION_ENABLED', false),
    // Stable HMAC key: do not rotate with META_APP_SECRET without rekeying actor digests.
    'meta_actor_hmac_key' => env('META_ACTOR_HMAC_KEY') ?: env('APP_KEY'),
    'grant_actor_hmac_key' => env('SOCIAL_GRANT_ACTOR_HMAC_KEY') ?: env('META_ACTOR_HMAC_KEY') ?: env('APP_KEY'),
    'meta_graph_version' => env('META_GRAPH_VERSION', 'v27.0'),
    'linkedin_api_version' => env('LINKEDIN_API_VERSION', '202610'),
    // Provider product/app review is separate from the global publication release. All default OFF.
    'publishing_entitlements' => [
        'facebook' => env('PLANNER_FACEBOOK_PUBLISHING_ENTITLED', false),
        'linkedin' => env('PLANNER_LINKEDIN_PUBLISHING_ENTITLED', false),
        'x' => env('PLANNER_X_PUBLISHING_ENTITLED', false),
    ],
    // LinkedIn organization access is restricted; enable only after Community Management approval.
    'linkedin_organizations_enabled' => env('LINKEDIN_ORGANIZATIONS_ENABLED', false),
    // Each provider uses a fixed, registered OAuth callback URI, never a user-provided redirect URL.
    'providers' => [
        'facebook' => [
            'client_id' => env('META_APP_ID'),
            'client_secret' => env('META_APP_SECRET'),
        ],
        'instagram' => [
            // Instagram API with Facebook Login; same Meta app, separate consent and connection record.
            'client_id' => env('META_APP_ID'),
            'client_secret' => env('META_APP_SECRET'),
        ],
        'linkedin' => [
            'client_id' => env('LINKEDIN_CLIENT_ID'),
            'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        ],
        'x' => [
            'client_id' => env('X_CLIENT_ID'),
            'client_secret' => env('X_CLIENT_SECRET'),
        ],
        'tiktok' => [
            'client_id' => env('TIKTOK_CLIENT_KEY'),
            'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        ],
        'youtube' => [
            'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
            'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        ],
    ],
];
