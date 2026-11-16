<?php

return [
    // OAuth login for remote MCP clients. Deliberately independent of Sanctum grants.
    'oauth_enabled' => env('PLANNER_OAUTH_ENABLED', false),
    'oauth_dcr_enabled' => env('PLANNER_OAUTH_DCR_ENABLED', false),
    'oauth_allowed_redirect_hosts' => env('PLANNER_OAUTH_ALLOWED_REDIRECT_HOSTS', 'chatgpt.com'),
    // Independent AI agent orchestration release controls; all default OFF.
    'agent_access_enabled' => env('PLANNER_AGENT_ACCESS_ENABLED', false),
    'agent_write_enabled' => env('PLANNER_AGENT_WRITE_ENABLED', false),
    'agent_autonomy_enabled' => env('PLANNER_AGENT_AUTONOMY_ENABLED', false),
    // Human-triggered, private download only; never imports or publishes media.
    'creative_image_enabled' => env('PLANNER_CREATIVE_IMAGE_ENABLED', false),
    // Package-owned binary upload only; explicitly gated behind verified Storage v3.
    'creative_auto_storage_enabled' => env('PLANNER_CREATIVE_AUTO_STORAGE_ENABLED', false),
    'creative_handoff_ttl_minutes' => (int) env('PLANNER_CREATIVE_HANDOFF_TTL_MINUTES', 60),
    'agent_delegated_publishing_enabled' => env('PLANNER_AGENT_DELEGATED_PUBLISHING_ENABLED', false),
    'agent_allowed_origins' => env('PLANNER_AGENT_ALLOWED_ORIGINS', ''),
    'integration_read_enabled' => env('PLANNER_INTEGRATION_READ_ENABLED', false),
    // Independent release switch for the first-party Laravel MCP server. Never enables writes.
    'mcp_read_enabled' => env('PLANNER_MCP_READ_ENABLED', false),
    'mcp_allowed_origins' => env('PLANNER_MCP_ALLOWED_ORIGINS', ''),
    'operational_alert_scan_enabled' => env('PLANNER_OPERATIONAL_ALERT_SCAN_ENABLED', false),
    'operational_alert_mail_enabled' => env('PLANNER_OPERATIONAL_ALERT_MAIL_ENABLED', false),
    // Separate from all publication/remote provider flags. Defaults OFF.
    'emergency_publish_stop' => env('PLANNER_EMERGENCY_PUBLISH_STOP', true),
    'text_dispatch_enabled' => env('PLANNER_TEXT_DISPATCH_ENABLED', false),
    'text_dispatch_provider_approved' => env('PLANNER_TEXT_DISPATCH_PROVIDER_APPROVED', false),
    'text_dispatch_released' => env('PLANNER_TEXT_DISPATCH_RELEASED', false),
    // Independent media gates: these NEVER enable the disabled multi-step engine.
    'media_dispatch_released' => env('PLANNER_MEDIA_DISPATCH_RELEASED', false),
    'media_worker_enabled' => env('PLANNER_MEDIA_WORKER_ENABLED', false),
    // Separately gated resumable uploads; requires an independently verified Tetranyble Storage byte reader.
    'youtube_resumable_enabled' => env('PLANNER_YOUTUBE_RESUMABLE_ENABLED', false),
    // Exact approved Tetranyble Storage CDN hostnames (comma-separated), never arbitrary URLs.
    'media_distribution_hosts' => env('PLANNER_MEDIA_DISTRIBUTION_HOSTS', ''),
    // Must be enabled only after pinned v3 package and real disk smoke tests pass.
    'storage_v3_adapter_verified' => env('PLANNER_STORAGE_V3_ADAPTER_VERIFIED', false),
    'media_delivery_origin_verified' => env('PLANNER_MEDIA_DELIVERY_ORIGIN_VERIFIED', false),
    'media_entitlements' => [
        'instagram' => env('PLANNER_INSTAGRAM_MEDIA_ENTITLED', false),
        'tiktok' => env('PLANNER_TIKTOK_MEDIA_ENTITLED', false),
        'youtube' => env('PLANNER_YOUTUBE_MEDIA_ENTITLED', false),
    ],
    // Read-only provider metrics; never grants publishing or permission to make social posts.
    'provider_metric_sync_enabled' => env('PLANNER_PROVIDER_METRIC_SYNC_ENABLED', false),
    // X read-only API may have a separate paid quota. Must be released independently.
    'x_metric_sync_enabled' => env('PLANNER_X_METRIC_SYNC_ENABLED', false),
    // Meta Page-only post media views. Requires pages_read_engagement, Page grant and app review.
    'facebook_metric_sync_enabled' => env('PLANNER_FACEBOOK_METRIC_SYNC_ENABLED', false),
    // TikTok creator-owned video.query reads use POST by API contract but never create or alter content.
    'tiktok_metric_sync_enabled' => env('PLANNER_TIKTOK_METRIC_SYNC_ENABLED', false),
    // Instagram business media views; separate Meta app review and owner verification.
    'instagram_metric_sync_enabled' => env('PLANNER_INSTAGRAM_METRIC_SYNC_ENABLED', false),
    'provider_metric_autoqueue_enabled' => env('PLANNER_PROVIDER_METRIC_AUTOQUEUE_ENABLED', false),
    'report_scheduler_enabled' => env('PLANNER_REPORT_SCHEDULER_ENABLED', false),
    // Review-only schedule projections; no automatic hold, approval, arm or publish.
    'recurring_expansion_enabled' => env('PLANNER_RECURRING_EXPANSION_ENABLED', false),
];
