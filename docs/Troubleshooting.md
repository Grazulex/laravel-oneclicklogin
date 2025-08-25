# Troubleshooting

This guide helps you resolve common issues with Laravel OneClickLogin package.

## Table of Contents

- [Common Issues](#common-issues)
- [Installation Problems](#installation-problems)
- [Configuration Issues](#configuration-issues)
- [Authentication Failures](#authentication-failures)
- [Database Problems](#database-problems)
- [Performance Issues](#performance-issues)
- [Security Concerns](#security-concerns)
- [Debug Tools](#debug-tools)

## Common Issues

### Magic Links Not Working

**Symptoms:**
- Magic links redirect to error page
- "Invalid token" errors
- Links appear to be consumed but authentication fails

**Possible Causes & Solutions:**

1. **Token mismatch in database**
```php
// Check if token is properly hashed
$link = MagicLink::first();
dd($link->token); // Should be hashed, not plain text
```

2. **Incorrect route configuration**
```php
// Ensure route is properly registered
Route::get('/magic-link/{token}', function ($token) {
    // Your consumption logic
})->name('magic-link.consume');
```

3. **Middleware conflicts**
```php
// Check middleware stack
Route::get('/magic-link/{token}', [MagicLinkController::class, 'consume'])
    ->middleware(['web']) // Only include necessary middleware
    ->name('magic-link.consume');
```

### Email Not Received

**Symptoms:**
- Magic link generation succeeds but no email arrives
- Emails go to spam folder

**Solutions:**

1. **Check mail configuration**
```bash
# Test mail configuration
php artisan tinker
>>> Mail::raw('Test message', function ($message) { $message->to('test@example.com')->subject('Test'); });
```

2. **Verify queue processing**
```bash
# If using queues, ensure worker is running
php artisan queue:work
```

3. **Check mail logs**
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log | grep -i mail
```

### Expired Link Errors

**Symptoms:**
- Links expire too quickly
- Users report links are expired when they shouldn't be

**Solutions:**

1. **Check server timezone**
```php
// In your controller or command
dd(now()); // Should match your expected timezone
dd(config('app.timezone')); // Should be correct
```

2. **Verify expiration settings**
```php
// Check default expiration
dd(config('sharelink.default_expiration_minutes'));

// Check specific link expiration
$link = MagicLink::find(1);
dd($link->expires_at, now());
```

3. **Database timezone issues**
```sql
-- Check database timezone
SELECT NOW(); -- MySQL
SELECT CURRENT_TIMESTAMP; -- PostgreSQL
```

---

## Installation Problems

### Composer Dependencies

**Error:** "Package not found"
```bash
# Clear composer cache
composer clear-cache

# Try installing with specific version
composer require grazulex/laravel-oneclicklogin:^1.0
```

**Error:** "Class not found"
```bash
# Regenerate autoload files
composer dump-autoload

# Clear Laravel caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Migration Issues

**Error:** "Table already exists"
```bash
# Check if migration was already run
php artisan migrate:status

# Rollback if needed
php artisan migrate:rollback --step=1

# Re-run migration
php artisan migrate
```

**Error:** "Column not found"
```bash
# Ensure you're running the latest migration
php artisan migrate:refresh

# Check migration file exists
ls database/migrations/*magic_links*
```

### Service Provider Registration

**Error:** "Service provider not found"

1. **Check config/app.php**
```php
'providers' => [
    // ...
    Grazulex\OneClickLogin\ShareLinkServiceProvider::class,
],
```

2. **Or use package discovery** (Laravel 5.5+)
```bash
# Ensure package.json includes auto-discovery
composer show grazulex/laravel-oneclicklogin
```

---

## Configuration Issues

### Config File Not Published

**Problem:** Configuration changes don't take effect

**Solution:**
```bash
# Publish configuration
php artisan vendor:publish --provider="Grazulex\OneClickLogin\ShareLinkServiceProvider" --tag="config"

# Clear config cache
php artisan config:clear
```

### Route Conflicts

**Problem:** Magic link routes conflict with existing routes

**Solution:**
```php
// In routes/web.php - define before catch-all routes
Route::get('/auth/magic-link/{token}', [MagicLinkController::class, 'consume'])
    ->name('magic-link.consume');

// Or use route model binding
Route::get('/magic/{magicLink}', [MagicLinkController::class, 'consume'])
    ->name('magic-link.consume');
```

### Environment Variables

**Problem:** Configuration not reading from .env file

**Solution:**
```bash
# Check .env file exists and has correct values
cat .env | grep -i magic

# Clear config cache
php artisan config:clear

# Verify config values
php artisan tinker
>>> config('sharelink.default_expiration_minutes')
```

---

## Authentication Failures

### User Not Found

**Problem:** Magic link valid but user doesn't exist

**Solution:**
```php
// Always check user exists before generating link
$user = User::where('email', $email)->first();
if (!$user) {
    // Handle user not found
    return back()->withErrors(['email' => 'User not found']);
}

$link = OneClickLogin::for($email)->generate();
```

### Authentication Loop

**Problem:** After consuming magic link, user redirected back to login

**Solution:**
```php
// Ensure user is properly authenticated
public function consume($token)
{
    $consumer = OneClickLogin::consume($token);
    $link = $consumer->consume();
    
    $user = User::where('email', $link->email)->first();
    
    // Explicitly authenticate user
    Auth::login($user, true); // true for remember
    
    // Verify authentication
    if (!Auth::check()) {
        return redirect()->route('login')->withErrors(['auth' => 'Authentication failed']);
    }
    
    return redirect($link->redirect_url ?: '/dashboard');
}
```

### Session Issues

**Problem:** Authentication doesn't persist across requests

**Solution:**
```php
// Check session configuration
// config/session.php
'lifetime' => 120,
'expire_on_close' => false,
'encrypt' => false,
'files' => storage_path('framework/sessions'),
'connection' => null,
'table' => 'sessions',
'store' => null,
'lottery' => [2, 100],
'cookie' => env('SESSION_COOKIE', 'laravel_session'),
'path' => '/',
'domain' => env('SESSION_DOMAIN', null),
'secure' => env('SESSION_SECURE_COOKIE', false),
'http_only' => true,
'same_site' => 'lax',
```

---

## Database Problems

### Connection Issues

**Problem:** Database connection errors during magic link operations

**Solution:**
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check database configuration
cat .env | grep DB_
```

### Performance Issues

**Problem:** Slow magic link operations

**Solution:**
```sql
-- Add missing indexes
CREATE INDEX idx_magic_links_token ON magic_links(token);
CREATE INDEX idx_magic_links_email ON magic_links(email);
CREATE INDEX idx_magic_links_expires_at ON magic_links(expires_at);
CREATE INDEX idx_magic_links_consumed_at ON magic_links(consumed_at);
```

### Table Corruption

**Problem:** Inconsistent data in magic_links table

**Solution:**
```bash
# Check table integrity (MySQL)
mysql -u username -p
CHECK TABLE magic_links;

# Repair if needed
REPAIR TABLE magic_links;

# For PostgreSQL
psql -U username -d database
REINDEX TABLE magic_links;
```

---

## Performance Issues

### Slow Link Generation

**Problem:** Magic link generation takes too long

**Debug:**
```php
// Add timing to generation
$start = microtime(true);
$link = OneClickLogin::for($email)->generate();
$duration = microtime(true) - $start;
Log::info("Link generation took {$duration} seconds");
```

**Solutions:**
1. **Database optimization**
```sql
-- Ensure proper indexes exist
SHOW INDEX FROM magic_links;
```

2. **Check for N+1 queries**
```php
// Enable query logging
DB::enableQueryLog();
$link = OneClickLogin::for($email)->generate();
dd(DB::getQueryLog());
```

### Memory Issues

**Problem:** High memory usage during batch operations

**Solution:**
```php
// Use chunking for large batches
User::chunk(100, function ($users) {
    foreach ($users as $user) {
        OneClickLogin::for($user->email)->generate();
    }
});

// Or use generators
function generateLinksGenerator($emails) {
    foreach ($emails as $email) {
        yield OneClickLogin::for($email)->generate();
    }
}
```

### Database Growth

**Problem:** magic_links table grows too large

**Solution:**
```bash
# Regular cleanup
php artisan magic-link:cleanup --older-than=30

# Schedule automatic cleanup
# In app/Console/Kernel.php
$schedule->command('magic-link:cleanup --force --older-than=7')->daily();
```

---

## Security Concerns

### Token Prediction

**Problem:** Concern about token security

**Verification:**
```php
// Check token randomness
$tokens = [];
for ($i = 0; $i < 1000; $i++) {
    $link = OneClickLogin::for('test@example.com')->generate();
    $tokens[] = $link->token;
}

// Analyze for patterns (should find none)
dd(array_unique($tokens) === $tokens); // Should be true
```

### Rate Limiting Bypass

**Problem:** Users bypassing rate limits

**Solution:**
```php
// Implement multiple rate limiting layers
public function generateLink(Request $request)
{
    $email = $request->input('email');
    
    // Rate limit by IP
    $ipKey = 'magic-link:ip:' . $request->ip();
    if (RateLimiter::tooManyAttempts($ipKey, 10)) {
        abort(429, 'Too many requests from this IP');
    }
    
    // Rate limit by email
    $emailKey = 'magic-link:email:' . $email;
    if (RateLimiter::tooManyAttempts($emailKey, 3)) {
        abort(429, 'Too many requests for this email');
    }
    
    // Rate limit globally
    $globalKey = 'magic-link:global';
    if (RateLimiter::tooManyAttempts($globalKey, 1000)) {
        abort(429, 'System temporarily unavailable');
    }
    
    RateLimiter::hit($ipKey, 3600);
    RateLimiter::hit($emailKey, 300);
    RateLimiter::hit($globalKey, 60);
    
    return OneClickLogin::for($email)->generate();
}
```

### Session Fixation

**Problem:** Potential session fixation attacks

**Solution:**
```php
// Regenerate session ID after authentication
public function consume($token)
{
    $consumer = OneClickLogin::consume($token);
    $link = $consumer->consume();
    
    $user = User::where('email', $link->email)->first();
    
    // Regenerate session
    session()->regenerate();
    
    // Then authenticate
    Auth::login($user);
    
    return redirect($link->redirect_url ?: '/dashboard');
}
```

---

## Debug Tools

### Enable Debug Mode

```php
// In .env
APP_DEBUG=true
LOG_LEVEL=debug

// Or temporarily in code
config(['app.debug' => true]);
```

### Custom Debug Commands

```php
// Create debug command
php artisan make:command DebugMagicLinks

// app/Console/Commands/DebugMagicLinks.php
class DebugMagicLinks extends Command
{
    protected $signature = 'magic-link:debug {email?}';
    
    public function handle()
    {
        $email = $this->argument('email') ?: 'debug@example.com';
        
        $this->info('Creating magic link for: ' . $email);
        
        $link = OneClickLogin::for($email)
            ->expiresInMinutes(60)
            ->context(['debug' => true])
            ->generate();
            
        $this->table(
            ['Property', 'Value'],
            [
                ['Email', $link->email],
                ['Token (first 10 chars)', substr($link->token, 0, 10) . '...'],
                ['Expires At', $link->expires_at],
                ['URL', $link->getUrl()],
                ['Context', json_encode($link->context)],
            ]
        );
        
        // Test consumption
        $consumer = OneClickLogin::consume($link->token);
        $this->info('Link valid: ' . ($consumer->isValid() ? 'Yes' : 'No'));
        $this->info('Can consume: ' . ($consumer->canUse() ? 'Yes' : 'No'));
    }
}
```

### Logging Configuration

```php
// In config/logging.php
'channels' => [
    'magic_links' => [
        'driver' => 'single',
        'path' => storage_path('logs/magic_links.log'),
        'level' => 'debug',
    ],
],

// Usage in your code
Log::channel('magic_links')->info('Magic link generated', [
    'email' => $email,
    'token' => substr($token, 0, 10) . '...',
    'expires_at' => $expiresAt,
]);
```

### Health Check Endpoint

```php
// Create health check route
Route::get('/health/magic-links', function () {
    $checks = [];
    
    // Check database connection
    try {
        MagicLink::count();
        $checks['database'] = 'OK';
    } catch (\Exception $e) {
        $checks['database'] = 'FAIL: ' . $e->getMessage();
    }
    
    // Check token generation
    try {
        $token = Str::random(64);
        hash('sha256', $token);
        $checks['token_generation'] = 'OK';
    } catch (\Exception $e) {
        $checks['token_generation'] = 'FAIL: ' . $e->getMessage();
    }
    
    // Check configuration
    $checks['config'] = [
        'default_expiration' => config('sharelink.default_expiration_minutes'),
        'table_name' => config('sharelink.table_name'),
        'cleanup_enabled' => config('sharelink.cleanup.enabled'),
    ];
    
    return response()->json($checks);
});
```

### Performance Monitoring

```php
// Add to AppServiceProvider
public function boot()
{
    if (app()->environment('local')) {
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'magic_links')) {
                Log::debug('Magic Links Query', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time,
                ]);
            }
        });
    }
}
```

These troubleshooting guides should help you resolve most common issues with Laravel OneClickLogin. If you encounter issues not covered here, check the package's GitHub issues or create a new issue with detailed information about your problem.
