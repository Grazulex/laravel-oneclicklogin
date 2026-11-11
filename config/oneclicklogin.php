<?php

declare(strict_types=1);

return [
    // Time-to-live for magic links in minutes
    'ttl_minutes' => (int) env('ONECLICKLOGIN_TTL_MINUTES', 15),

    // Magic links are single-use: a link is consumed on its first successful use.

    // Route name used to build magic link URLs.
    // Falls back to the package route 'magic-link.verify' when this route does not exist.
    'signed_route_name' => env('ONECLICKLOGIN_SIGNED_ROUTE_NAME', 'magic-link.verify'),

    // Authentication guard to use
    'guard' => env('ONECLICKLOGIN_GUARD', 'web'),

    // User model class
    'user_model' => env('ONECLICKLOGIN_USER_MODEL', 'App\\Models\\User'),

    // Email field name in the user model
    'email_field' => env('ONECLICKLOGIN_EMAIL_FIELD', 'email'),

    // Allow unknown users (redirect to registration)
    'allow_unknown_users' => env('ONECLICKLOGIN_ALLOW_UNKNOWN_USERS', false),

    // Default redirect URL after successful login
    'default_redirect_url' => env('ONECLICKLOGIN_DEFAULT_REDIRECT_URL', '/dashboard'),

    // IP binding - if enabled, links generated during an HTTP request are tied to
    // the requester's IP address and can only be consumed from that IP
    'ip_binding' => env('ONECLICKLOGIN_IP_BINDING', false),

    // Device binding - if enabled, links generated during an HTTP request are tied to
    // the requester's user agent and can only be consumed from that device
    'device_binding' => env('ONECLICKLOGIN_DEVICE_BINDING', false),

    // MultiPersona integration
    'multi_persona' => [
        'enabled' => env('ONECLICKLOGIN_MULTI_PERSONA_ENABLED', true),
        'keys' => ['persona', 'tenant', 'role'],
    ],

    // Redirect URLs
    'redirect_after_login' => env('ONECLICKLOGIN_REDIRECT_AFTER_LOGIN', '/'),
    // Where browsers are sent when a magic link is rejected (invalid, expired, used...)
    'redirect_on_invalid' => env('ONECLICKLOGIN_REDIRECT_ON_INVALID', '/login?invalid=1'),

    // Notification classes
    'notifications' => [
        'mail' => env('ONECLICKLOGIN_MAIL_NOTIFICATION', 'App\Notifications\MagicLinkMail'),
        'sms' => env('ONECLICKLOGIN_SMS_NOTIFICATION', 'App\Notifications\MagicLinkSms'),
    ],

    // Rate limiting (set a value to 0 to disable that limit)
    'rate_limit' => [
        // Magic links generated per email address per hour
        'issue_per_email_per_hour' => (int) env('ONECLICKLOGIN_RATE_ISSUE_PER_EMAIL_PER_HOUR', 5),
        // Verification attempts per client (IP + user agent) per minute
        'consume_per_ip_per_min' => (int) env('ONECLICKLOGIN_RATE_CONSUME_PER_IP_PER_MIN', 20),
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
        // Hash algorithm for stored tokens (sha256, bcrypt, argon2id).
        // Links hashed with another algorithm remain valid.
        'hash_algorithm' => env('ONECLICKLOGIN_HASH_ALGORITHM', 'sha256'),
        // Links are found through the indexed token_lookup column. Links created
        // before that column existed (bcrypt/argon2id hashes without token_lookup)
        // can only be found by verifying them one by one: disable this once they
        // have all expired or been pruned.
        'legacy_token_scan' => env('ONECLICKLOGIN_LEGACY_TOKEN_SCAN', true),
        // Token length in bytes (minimum 32 for security)
        'token_length' => (int) env('ONECLICKLOGIN_TOKEN_LENGTH', 32),
        // Sign magic link URLs (expiring with the link) and reject unsigned or tampered URLs
        'signed_urls' => env('ONECLICKLOGIN_SIGNED_URLS', false),
        // Generate https:// magic link URLs and reject verification over plain HTTP
        // (behind a proxy, make sure TrustProxies is configured)
        'require_https' => env('ONECLICKLOGIN_REQUIRE_HTTPS', false),
    ],
];
