# Frequently Asked Questions (FAQ)

CoYou can customize the table name in the configuration:

```php
// config/oneclicklogin.php
'table_name' => 'custom_magic_links',
```uestions and answers about Laravel OneClickLogin package.

## General Questions

### What is Laravel OneClickLogin?

Laravel OneClickLogin is a Laravel package that provides passwordless authentication using magic links. Users receive a secure, time-limited link via email that automatically logs them in when clicked.

### Is it secure?

Yes, the package implements several security measures:
- Cryptographically secure token generation
- Token hashing in database
- Time-limited links (configurable expiration)
- Rate limiting for link generation
- Optional IP and user agent validation
- Signed URLs for additional security

### Does it work with existing authentication systems?

Yes, OneClickLogin is designed to complement your existing authentication system. It can be used alongside traditional password-based login or as a complete replacement.

### What Laravel versions are supported?

The package supports Laravel 11.0+ and 12.0+. It requires PHP 8.3 or higher.

---

## Installation & Setup

### Do I need to modify my User model?

No modifications to your User model are required. The package works with any model that implements Laravel's `Authenticatable` contract.

### Can I customize the database table?

Yes, you can customize the table name in the configuration:

```php
// config/oneclicklogin.php
'table_name' => 'custom_magic_links',
```

### How do I handle email sending?

The package generates magic links but doesn't send emails automatically. You need to implement email sending in your application:

```php
$link = OneClickLogin::for('user@example.com')->generate();

// Send via your preferred method
Mail::to('user@example.com')->send(new MagicLinkMail($link));
```

---

## Usage Questions

### How long do magic links last by default?

Magic links expire after 15 minutes by default. You can customize this:

```php
// Global default in config/oneclicklogin.php
'ttl_minutes' => 30,

// Or per-link
$link = OneClickLogin::for('user@example.com')
    ->expiresInMinutes(60)
    ->generate();
```

### Can I use magic links for specific actions?

Yes, you can add context data to links for specific use cases:

```php
$link = OneClickLogin::for('user@example.com')
    ->context([
        'action' => 'password_reset',
        'user_id' => 123,
        'permissions' => ['admin']
    ])
    ->generate();
```

### How do I redirect users after login?

Set a redirect URL when generating the link:

```php
$link = OneClickLogin::for('user@example.com')
    ->redirectTo('/dashboard')
    ->generate();
```

### Can I regenerate a magic link?

No, each magic link is single-use. Generate a new link if needed:

```php
// Revoke old link (optional)
OneClickLogin::revoke($oldToken);

// Generate new link
$newLink = OneClickLogin::for('user@example.com')->generate();
```

---

## Environment & Debugging Questions ⭐ **NEW**

### Why does OneClickLogin::for()->generate() fail but CLI commands work?

**TL;DR**: This is almost always an environment setup issue, not a package bug.

**Common scenario:**
```php
// ❌ This fails in your app
$link = OneClickLogin::for('user@example.com')->generate();
// Error: Carbon type issues, database errors, etc.

// ✅ But this works
php artisan oneclicklogin:generate user@example.com
```

**Root causes:**
1. **Missing migrations** - `magic_links` table doesn't exist
2. **Dirty environment** - Cached configuration or database state
3. **Test context** - Running in wrong Laravel context

**Quick fix:**
```bash
# Clean everything and retry
php artisan migrate:fresh
php artisan cache:clear
php artisan config:clear

# Then test
php artisan tinker
>>> OneClickLogin::for('test@example.com')->generate();
```

**For tests, always use:**
```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class YourTest extends TestCase
{
    use RefreshDatabase; // ← This solves 90% of issues
}
```

### Why do I get Carbon type casting errors?

These errors typically indicate environment issues, not code bugs:

```
Carbon::rawAddUnit(): Argument #3 ($value) must be of type int|float, string given
```

**Debugging steps:**
1. Verify configuration types: `php artisan tinker --execute="var_dump(config('oneclicklogin.ttl_minutes'));"`
2. Check database: `php artisan migrate:status`
3. Clear environment: `php artisan config:clear && php artisan cache:clear`
4. Test in isolation: Use `RefreshDatabase` in tests

**99% of the time**: It's not a package bug, it's environment state.

---

## Security Questions

### What happens if someone intercepts the email?

Magic links are time-limited and single-use, minimizing the window of vulnerability. Consider:
- Using short expiration times for sensitive operations
- Implementing IP validation for high-security applications
- Using HTTPS for all magic link URLs

### Can I require the same IP address?

Yes, configure IP validation in your application:

```php
// Store IP when generating
$link = OneClickLogin::for('user@example.com')
    ->context(['ip_address' => request()->ip()])
    ->generate();

// Validate IP when consuming
$consumer = OneClickLogin::consume($token);
$link = $consumer->getLink();

if ($link->context['ip_address'] !== request()->ip()) {
    throw new SecurityException('IP address mismatch');
}
```

### How are tokens generated?

Tokens are generated using PHP's `random_bytes()` function for cryptographic security, then hashed using SHA-256 before database storage.

### What about rate limiting?

The package includes configurable rate limiting:

```php
// config/oneclicklogin.php
'rate_limiting' => [
    'enabled' => true,
    'max_attempts' => 3,
    'decay_minutes' => 5,
],
```

---

## Performance Questions

### Will this slow down my application?

The package is optimized for performance:
- Minimal database queries
- Efficient token generation
- Automatic cleanup of expired links
- Proper database indexing

### How do I handle high traffic?

For high-traffic applications:
- Use database indexes on frequently queried columns
- Implement queue-based email sending
- Regular cleanup of expired links
- Consider Redis for rate limiting

### Should I clean up expired links?

Yes, regular cleanup is recommended:

```bash
# Manual cleanup
php artisan magic-link:cleanup

# Scheduled cleanup (in app/Console/Kernel.php)
$schedule->command('magic-link:cleanup --force')->daily();
```

---

## Integration Questions

### Can I use this with APIs?

Yes, the package works well with API authentication:

```php
// Generate link
$link = OneClickLogin::for('user@example.com')->generate();

// Return JSON response
return response()->json([
    'magic_url' => $link->getUrl(),
    'expires_at' => $link->expires_at
]);
```

### Does it work with Single Page Applications (SPAs)?

Yes, see our [SPA example](Examples-SPA) for a complete Vue.js implementation with token-based authentication.

### Can I use it in multi-tenant applications?

Absolutely! See our [Multi-Tenant example](Examples-Multi-Tenant) for organization-based tenancy implementation.

### How do I integrate with my existing middleware?

Magic link consumption routes should use minimal middleware:

```php
Route::get('/magic-link/{token}', [MagicLinkController::class, 'consume'])
    ->middleware(['web']) // Only essential middleware
    ->name('magic-link.consume');
```

---

## Testing Questions

### How do I test magic link functionality?

The package provides testing-friendly methods:

```php
public function test_magic_link_generation()
{
    $link = OneClickLogin::for('test@example.com')->generate();
    
    $this->assertNotNull($link);
    $this->assertEquals('test@example.com', $link->email);
    $this->assertFalse($link->isExpired());
}

public function test_magic_link_consumption()
{
    $link = OneClickLogin::for('test@example.com')->generate();
    $consumer = OneClickLogin::consume($link->token);
    
    $this->assertTrue($consumer->isValid());
    $this->assertTrue($consumer->canUse());
    
    $consumedLink = $consumer->consume();
    $this->assertNotNull($consumedLink->consumed_at);
}
```

### Should I disable email sending in tests?

Yes, use Laravel's mail faking:

```php
public function test_magic_link_email()
{
    Mail::fake();
    
    // Your magic link generation code
    $link = OneClickLogin::for('test@example.com')->generate();
    Mail::to('test@example.com')->send(new MagicLinkMail($link));
    
    Mail::assertSent(MagicLinkMail::class);
}
```

---

## Troubleshooting Questions

### Magic links show "Invalid token" error

Check these common causes:
1. Token mismatch - ensure you're using the complete token
2. Database issues - verify the magic_links table exists
3. Route conflicts - ensure routes are properly configured
4. Middleware conflicts - use minimal middleware on consumption routes

### Users aren't being authenticated after clicking links

Verify:
1. User exists in database with matching email
2. `Auth::login()` is called after successful consumption
3. Session configuration is correct
4. No middleware blocking authentication

### Links expire too quickly

Check:
1. Server timezone configuration
2. Database timezone settings
3. Expiration time configuration
4. System clock synchronization

### Email links don't work in some email clients

Ensure:
1. URLs are properly formatted
2. No line breaks in the URL
3. Use absolute URLs with HTTPS
4. Test with different email clients

---

## Migration Questions

### How do I migrate from another authentication system?

1. Install OneClickLogin alongside existing system
2. Gradually migrate user workflows
3. Keep existing authentication as fallback
4. Monitor usage and success rates
5. Eventually deprecate old system

### Can I import existing magic links?

The package doesn't provide direct import functionality, but you can create new links for existing users:

```php
User::chunk(100, function ($users) {
    foreach ($users as $user) {
        OneClickLogin::for($user->email)
            ->expiresInDays(7)
            ->generate();
    }
});
```

### How do I backup magic link data?

Include the magic_links table in your regular database backups:

```bash
# MySQL
mysqldump -u user -p database magic_links > magic_links_backup.sql

# PostgreSQL
pg_dump -U user -d database -t magic_links > magic_links_backup.sql
```

---

## Advanced Questions

### Can I extend the package functionality?

Yes, the package is designed for extensibility:

1. **Custom events**: Listen to `MagicLinkGenerated`, `MagicLinkConsumed` events
2. **Custom commands**: Create your own Artisan commands
3. **Custom middleware**: Add custom validation logic
4. **Service extension**: Extend the `MagicLinkManager` service

### How do I add custom validation?

Extend the consumption logic:

```php
public function consume($token)
{
    $consumer = OneClickLogin::consume($token);
    
    if (!$consumer->isValid()) {
        throw new InvalidTokenException();
    }
    
    $link = $consumer->getLink();
    
    // Custom validation
    if (!$this->validateCustomRules($link)) {
        throw new CustomValidationException();
    }
    
    return $consumer->consume();
}
```

### Can I use multiple databases?

Yes, configure the database connection:

```php
// config/oneclicklogin.php
'connection' => 'custom_database',
```

---

## Still Have Questions?

If your question isn't answered here:

1. Check the [Troubleshooting](Troubleshooting) guide
2. Search existing [GitHub Issues](https://github.com/Grazulex/laravel-oneclicklogin/issues)
3. Create a new issue with detailed information
4. Join our community discussions

**Need immediate help?** Include these details when asking for help:
- Laravel version
- PHP version
- Package version
- Error messages
- Relevant code snippets
- Steps to reproduce the issue
