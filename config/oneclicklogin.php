<?php

declare(strict_types=1);

return [
    // Time-to-live for magic links in minutes
    'ttl_minutes' => env('ONECLICKLOGIN_TTL_MINUTES', 15),

    // Maximum number of uses per magic link
    'max_uses' => env('ONECLICKLOGIN_MAX_USES', 1),

    // Route name for consuming magic links
    'signed_route_name' => 'oneclicklogin.consume',

    // Authentication guard to use
    'guard' => env('ONECLICKLOGIN_GUARD', 'web'),

    // IP binding - if enabled, magic links are tied to the IP address
    'ip_binding' => env('ONECLICKLOGIN_IP_BINDING', false),

    // Device binding - if enabled, magic links are tied to device fingerprint
    'device_binding' => env('ONECLICKLOGIN_DEVICE_BINDING', false),

    // Enable OTP step-up authentication for suspicious devices
    'enable_otp_step_up' => env('ONECLICKLOGIN_ENABLE_OTP_STEP_UP', false),

    // OTP provider (if step-up is enabled)
    'otp_provider' => env('ONECLICKLOGIN_OTP_PROVIDER', null),

    // MultiPersona integration
    'multi_persona' => [
        'enabled' => env('ONECLICKLOGIN_MULTI_PERSONA_ENABLED', true),
        'keys' => ['persona', 'tenant', 'role'],
    ],

    // Redirect URLs
    'redirect_after_login' => env('ONECLICKLOGIN_REDIRECT_AFTER_LOGIN', '/'),
    'redirect_on_invalid' => env('ONECLICKLOGIN_REDIRECT_ON_INVALID', '/login?invalid=1'),

    // Notification classes
    'notifications' => [
        'mail' => env('ONECLICKLOGIN_MAIL_NOTIFICATION', 'App\Notifications\MagicLinkMail'),
        'sms' => env('ONECLICKLOGIN_SMS_NOTIFICATION', 'App\Notifications\MagicLinkSms'),
    ],

    // Rate limiting
    'rate_limit' => [
        'issue_per_email_per_hour' => env('ONECLICKLOGIN_RATE_ISSUE_PER_EMAIL_PER_HOUR', 5),
        'consume_per_ip_per_min' => env('ONECLICKLOGIN_RATE_CONSUME_PER_IP_PER_MIN', 20),
    ],

    // Routes configuration
    'route' => [
        'prefix' => env('ONECLICKLOGIN_ROUTE_PREFIX', 'login'),
        'middleware' => [
            'Grazulex\OneClickLogin\Http\Middleware\EnsureMagicLinkIsValid',
        ],
    ],

    // Management interface
    'management' => [
        'enabled' => env('ONECLICKLOGIN_MANAGEMENT_ENABLED', true),
        'middleware' => [
            // e.g., 'web', 'auth' — left empty by default for tests
        ],
        // Optional Gate ability to authorize management actions (revoke/extend) against the MagicLink model
        'gate' => env('ONECLICKLOGIN_MANAGEMENT_GATE', null),
    ],

    // Scheduled tasks
    'schedule' => [
        'prune' => [
            'enabled' => env('ONECLICKLOGIN_SCHEDULE_PRUNE', true),
            // Cron expression or aliases like '@daily'; defaults to 3:00 AM daily
            'expression' => env('ONECLICKLOGIN_SCHEDULE_PRUNE_EXPRESSION', '0 3 * * *'),
            'description' => 'oneclicklogin:prune',
        ],
    ],

    // Observability
    'observability' => [
        'enabled' => env('ONECLICKLOGIN_OBSERVABILITY_ENABLED', true),
        'log' => env('ONECLICKLOGIN_OBSERVABILITY_LOG', true),
        'metrics' => env('ONECLICKLOGIN_OBSERVABILITY_METRICS', false),
        // no tokens/IPs in logs; only non-PII fields are included
    ],

    // User tracking
    'user_tracking' => [
        // Enable user tracking (created_by column)
        'enabled' => env('ONECLICKLOGIN_USER_TRACKING_ENABLED', false),
        // Type of user ID: 'bigint', 'uuid', 'ulid'
        'user_id_type' => env('ONECLICKLOGIN_USER_ID_TYPE', 'bigint'),
        // User table name
        'user_table' => env('ONECLICKLOGIN_USER_TABLE', 'users'),
        // Add foreign key constraint (set to false if you want to handle it manually)
        'add_foreign_key' => env('ONECLICKLOGIN_ADD_FOREIGN_KEY', true),
    ],

    // ShareLink integration (optional delivery layer)
    'sharelink' => [
        'enabled' => env('ONECLICKLOGIN_SHARELINK_ENABLED', false),
        'analytics' => env('ONECLICKLOGIN_SHARELINK_ANALYTICS', true),
        'audit_trails' => env('ONECLICKLOGIN_SHARELINK_AUDIT_TRAILS', true),
    ],

    // Security settings
    'security' => [
        // Hash algorithm for tokens (sha256, bcrypt, argon2id)
        'hash_algorithm' => env('ONECLICKLOGIN_HASH_ALGORITHM', 'sha256'),
        // Token length in bytes (minimum 32 for security)
        'token_length' => env('ONECLICKLOGIN_TOKEN_LENGTH', 32),
        // Enable signed URLs for additional protection
        'signed_urls' => env('ONECLICKLOGIN_SIGNED_URLS', true),
        // Require HTTPS for magic links
        'require_https' => env('ONECLICKLOGIN_REQUIRE_HTTPS', true),
    ],
];
