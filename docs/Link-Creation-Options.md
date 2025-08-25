# Magic Link Creation Options

This guide covers all available options and configurations for creating magic links with Laravel OneClickLogin.

## Table of Contents

- [Basic Creation](#basic-creation)
- [Expiration Options](#expiration-options)
- [Context Data](#context-data)
- [Redirect Configuration](#redirect-configuration)
- [Security Options](#security-options)
- [Advanced Configuration](#advanced-configuration)
- [Best Practices](#best-practices)

## Basic Creation

### Simple Magic Link

The most basic way to create a magic link:

```php
use Grazulex\OneClickLogin\Facades\OneClickLogin;

$link = OneClickLogin::for('user@example.com')->generate();
```

### With Redirect URL

Specify where users should be redirected after successful authentication:

```php
$link = OneClickLogin::for('user@example.com')
    ->redirectTo('/dashboard')
    ->generate();
```

### Full Example

```php
$link = OneClickLogin::for('user@example.com')
    ->redirectTo('/welcome')
    ->expiresInMinutes(30)
    ->context(['source' => 'registration'])
    ->generate();

echo $link->getUrl(); // Full magic link URL
```

---

## Expiration Options

Control when magic links expire to balance security and user experience.

### Time-Based Expiration

#### Minutes

```php
// Expires in 15 minutes (default)
$link = OneClickLogin::for('user@example.com')
    ->expiresInMinutes(15)
    ->generate();

// Expires in 5 minutes (high security)
$link = OneClickLogin::for('user@example.com')
    ->expiresInMinutes(5)
    ->generate();

// Expires in 60 minutes (user-friendly)
$link = OneClickLogin::for('user@example.com')
    ->expiresInMinutes(60)
    ->generate();
```

#### Hours

```php
// Expires in 2 hours
$link = OneClickLogin::for('user@example.com')
    ->expiresInHours(2)
    ->generate();

// Expires in 24 hours
$link = OneClickLogin::for('user@example.com')
    ->expiresInHours(24)
    ->generate();
```

#### Days

```php
// Expires in 1 day
$link = OneClickLogin::for('user@example.com')
    ->expiresInDays(1)
    ->generate();

// Expires in 7 days (for invitations)
$link = OneClickLogin::for('user@example.com')
    ->expiresInDays(7)
    ->generate();
```

### Specific Date/Time

```php
use Carbon\Carbon;

// Expires at specific time today
$link = OneClickLogin::for('user@example.com')
    ->expiresAt(Carbon::today()->addHours(18)) // 6 PM today
    ->generate();

// Expires at end of business hours
$link = OneClickLogin::for('user@example.com')
    ->expiresAt(Carbon::now()->setTime(17, 0)) // 5 PM
    ->generate();

// Expires next Monday at 9 AM
$link = OneClickLogin::for('user@example.com')
    ->expiresAt(Carbon::now()->next(Carbon::MONDAY)->setTime(9, 0))
    ->generate();
```

### Dynamic Expiration

```php
// Different expiration based on user type
$user = User::where('email', 'user@example.com')->first();

$expiration = match($user->type) {
    'admin' => 120, // 2 hours for admins
    'premium' => 60, // 1 hour for premium users
    'basic' => 30,   // 30 minutes for basic users
    default => 15    // 15 minutes default
};

$link = OneClickLogin::for($user->email)
    ->expiresInMinutes($expiration)
    ->generate();
```

---

## Context Data

Add metadata to magic links for enhanced functionality and tracking.

### Basic Context

```php
$link = OneClickLogin::for('user@example.com')
    ->context([
        'source' => 'mobile_app',
        'version' => '2.1.0',
        'device_type' => 'ios'
    ])
    ->generate();
```

### User Information

```php
$user = User::find(123);

$link = OneClickLogin::for($user->email)
    ->context([
        'user_id' => $user->id,
        'user_role' => $user->role,
        'first_login' => $user->last_login_at === null,
        'preferred_language' => $user->locale ?? 'en',
    ])
    ->generate();
```

### Application Context

```php
$link = OneClickLogin::for('user@example.com')
    ->context([
        'feature' => 'password_reset',
        'request_id' => Str::uuid(),
        'triggered_by' => auth()->id(),
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'referrer' => request()->header('referer'),
    ])
    ->generate();
```

### Business Logic Context

```php
// E-commerce example
$link = OneClickLogin::for($customer->email)
    ->context([
        'cart_id' => $cart->id,
        'total_amount' => $cart->total,
        'item_count' => $cart->items->count(),
        'promocode' => $cart->promocode,
        'checkout_step' => 'payment',
    ])
    ->generate();

// Multi-tenant example
$link = OneClickLogin::for($user->email)
    ->context([
        'organization_id' => $organization->id,
        'organization_slug' => $organization->slug,
        'user_role' => $user->getRoleInOrganization($organization),
        'permissions' => $user->getPermissionsInOrganization($organization),
        'trial_expires' => $organization->trial_ends_at,
    ])
    ->generate();
```

### Tracking and Analytics

```php
$link = OneClickLogin::for('user@example.com')
    ->context([
        // Campaign tracking
        'utm_source' => request()->get('utm_source'),
        'utm_medium' => request()->get('utm_medium'),
        'utm_campaign' => request()->get('utm_campaign'),
        
        // Internal tracking
        'flow_id' => session('flow_id'),
        'ab_test_variant' => session('ab_test_variant'),
        'conversion_goal' => 'signup_completion',
        
        // User journey
        'previous_page' => request()->header('referer'),
        'session_duration' => session('start_time') ? now()->diffInMinutes(session('start_time')) : 0,
        'page_views' => session('page_views', 0),
    ])
    ->generate();
```

---

## Redirect Configuration

Control where users go after successful authentication.

### Static Redirects

```php
// Simple redirect
$link = OneClickLogin::for('user@example.com')
    ->redirectTo('/dashboard')
    ->generate();

// Absolute URL
$link = OneClickLogin::for('user@example.com')
    ->redirectTo('https://app.example.com/welcome')
    ->generate();
```

### Dynamic Redirects

```php
// Redirect based on user role
$user = User::where('email', 'user@example.com')->first();

$redirectUrl = match($user->role) {
    'admin' => '/admin/dashboard',
    'manager' => '/manager/overview',
    'user' => '/user/profile',
    default => '/dashboard'
};

$link = OneClickLogin::for($user->email)
    ->redirectTo($redirectUrl)
    ->generate();
```

### Conditional Redirects

```php
// Redirect to intended URL or default
$intendedUrl = session('url.intended', '/dashboard');

$link = OneClickLogin::for('user@example.com')
    ->redirectTo($intendedUrl)
    ->generate();

// Redirect based on context
$isFirstLogin = User::where('email', 'user@example.com')->first()->last_login_at === null;

$link = OneClickLogin::for('user@example.com')
    ->redirectTo($isFirstLogin ? '/welcome/tour' : '/dashboard')
    ->context(['first_login' => $isFirstLogin])
    ->generate();
```

### Query Parameters

```php
// Add query parameters to redirect URL
$baseUrl = '/dashboard';
$params = http_build_query([
    'welcome' => 'true',
    'source' => 'magic_link',
    'timestamp' => now()->timestamp,
]);

$link = OneClickLogin::for('user@example.com')
    ->redirectTo($baseUrl . '?' . $params)
    ->generate();
```

---

## Security Options

Configure security features for enhanced protection.

### IP Address Validation

```php
// Store IP for validation (configure in config/oneclicklogin.php)
$link = OneClickLogin::for('user@example.com')
    ->context([
        'ip_address' => request()->ip(),
        'require_same_ip' => true,
    ])
    ->generate();
```

### User Agent Validation

```php
// Store user agent for validation
$link = OneClickLogin::for('user@example.com')
    ->context([
        'user_agent' => request()->userAgent(),
        'require_same_user_agent' => true,
    ])
    ->generate();
```

### Rate Limiting

```php
use Illuminate\Support\Facades\RateLimiter;

// Check rate limit before generating
$key = 'magic-link:' . request()->ip() . ':' . $email;

if (RateLimiter::tooManyAttempts($key, 3)) {
    $seconds = RateLimiter::availableIn($key);
    throw new \Exception("Too many requests. Try again in {$seconds} seconds.");
}

RateLimiter::hit($key, 300); // 5 minutes

$link = OneClickLogin::for($email)->generate();
```

### Secure Context Data

```php
// Encrypt sensitive context data
$sensitiveData = encrypt([
    'credit_card_last_four' => '1234',
    'bank_account' => 'xxx-xx-1234',
]);

$link = OneClickLogin::for('user@example.com')
    ->context([
        'encrypted_data' => $sensitiveData,
        'public_data' => 'safe information',
    ])
    ->generate();
```

---

## Advanced Configuration

### Custom Token Length

```php
// Configure in config/oneclicklogin.php
'token' => [
    'length' => 128, // Longer token for higher security
    'hash_algo' => 'sha256',
],
```

### Database Customization

```php
// Custom table name
'table_name' => 'custom_magic_links',

// Custom connection
'connection' => 'custom_database',
```

### URL Signing

```php
// Enable signed URLs for additional security
'url' => [
    'route_name' => 'magic-link.consume',
    'signed' => true,
    'expires' => 3600, // URL signature expires in 1 hour
],
```

### Batch Creation

```php
// Create multiple links efficiently
$emails = ['user1@example.com', 'user2@example.com', 'user3@example.com'];
$links = [];

foreach ($emails as $email) {
    $links[] = OneClickLogin::for($email)
        ->redirectTo('/welcome')
        ->expiresInHours(24)
        ->context(['batch_id' => Str::uuid()])
        ->generate();
}
```

### Conditional Creation

```php
// Only create if user exists
$email = 'user@example.com';
$user = User::where('email', $email)->first();

if (!$user) {
    throw new \Exception('User not found');
}

// Only create if user is active
if (!$user->is_active) {
    throw new \Exception('User account is inactive');
}

// Only create if user has permission
if (!$user->can('use_magic_links')) {
    throw new \Exception('User not authorized for magic links');
}

$link = OneClickLogin::for($email)
    ->context(['user_id' => $user->id])
    ->generate();
```

---

## Best Practices

### Security Best Practices

1. **Short Expiration Times**: Use short expiration times for sensitive operations
```php
// For password reset
$link = OneClickLogin::for($email)->expiresInMinutes(15)->generate();

// For login
$link = OneClickLogin::for($email)->expiresInMinutes(30)->generate();

// For invitations
$link = OneClickLogin::for($email)->expiresInDays(7)->generate();
```

2. **Context Validation**: Always validate context data when consuming
```php
// When consuming
$consumer = OneClickLogin::consume($token);
$link = $consumer->getLink();

// Validate context
if ($link->context['user_id'] !== auth()->id()) {
    throw new \Exception('Invalid user context');
}
```

3. **Rate Limiting**: Implement rate limiting for link generation
```php
// In your controller
$key = 'magic-link:' . request()->ip() . ':' . $email;
if (RateLimiter::tooManyAttempts($key, 3)) {
    return back()->withErrors(['email' => 'Too many requests']);
}
```

### Performance Best Practices

1. **Cleanup Strategy**: Regular cleanup of expired links
```php
// Schedule in app/Console/Kernel.php
$schedule->command('magic-link:cleanup --force')->daily();
```

2. **Database Indexing**: Ensure proper indexes exist
```sql
-- Add indexes for better performance
CREATE INDEX magic_links_email_index ON magic_links (email);
CREATE INDEX magic_links_expires_at_index ON magic_links (expires_at);
CREATE INDEX magic_links_consumed_at_index ON magic_links (consumed_at);
```

3. **Batch Operations**: Use batch operations for multiple links
```php
// Instead of multiple individual creates
$links = collect($emails)->map(function ($email) {
    return OneClickLogin::for($email)->generate();
});
```

### User Experience Best Practices

1. **Clear Expiration Communication**: Always communicate expiration times
```php
$link = OneClickLogin::for($email)->expiresInMinutes(15)->generate();

// In email template
$expiresIn = $link->expires_at->diffForHumans();
// "This link expires in 15 minutes"
```

2. **Graceful Error Handling**: Provide helpful error messages
```php
try {
    $consumer = OneClickLogin::consume($token);
    $link = $consumer->consume();
} catch (ExpiredLinkException $e) {
    return back()->with('error', 'This link has expired. Please request a new one.');
} catch (ConsumedLinkException $e) {
    return back()->with('error', 'This link has already been used.');
}
```

3. **Progress Indication**: Show progress for multi-step flows
```php
$link = OneClickLogin::for($email)
    ->context([
        'step' => 'email_verification',
        'total_steps' => 3,
        'next_step' => 'profile_completion',
    ])
    ->generate();
```

### Monitoring Best Practices

1. **Track Usage Metrics**: Monitor link creation and consumption
```php
// Log metrics
Log::info('Magic link generated', [
    'email' => $email,
    'expires_at' => $link->expires_at,
    'context' => $link->context,
]);
```

2. **Alert on Suspicious Activity**: Monitor for security issues
```php
// In a scheduled job
$recentFailures = MagicLink::where('created_at', '>', now()->subHour())
    ->whereNotNull('consumed_at')
    ->where('consumed_at', '>', 'expires_at')
    ->count();

if ($recentFailures > 10) {
    // Send security alert
}
```

3. **Regular Health Checks**: Monitor system health
```php
// Check for growing number of unconsumed links
$oldUnconsumed = MagicLink::where('created_at', '<', now()->subDays(7))
    ->whereNull('consumed_at')
    ->count();

if ($oldUnconsumed > 1000) {
    // Investigation needed
}
```

This comprehensive guide covers all aspects of creating magic links with Laravel OneClickLogin, from basic usage to advanced security and performance considerations.
