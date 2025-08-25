# API Reference

This document provides a comprehensive reference for the Laravel OneClickLogin package API.

## Table of Contents

- [Facades](#facades)
- [Services](#services)
- [Models](#models)
- [Events](#events)
- [Console Commands](#console-commands)
- [Configuration](#configuration)

## Facades

### OneClickLogin Facade

The main facade for interacting with the magic link system.

```php
use Grazulex\OneClickLogin\Facades\OneClickLogin;
```

#### Methods

##### `for(string $email): MagicLinkBuilder`

Creates a new magic link builder for the specified email address.

**Parameters:**
- `$email` (string): The email address to create the magic link for

**Returns:** `MagicLinkBuilder` instance

**Example:**
```php
$builder = OneClickLogin::for('user@example.com');
```

##### `consume(string $token): MagicLinkConsumer`

Creates a consumer for verifying and consuming magic links.

**Parameters:**
- `$token` (string): The magic link token to consume

**Returns:** `MagicLinkConsumer` instance

**Example:**
```php
$consumer = OneClickLogin::consume($token);
```

##### `cleanup(): int`

Removes expired magic links from the database.

**Returns:** Number of deleted records

**Example:**
```php
$deletedCount = OneClickLogin::cleanup();
```

---

## Services

### MagicLinkBuilder

Builder class for creating magic links with fluent interface.

#### Methods

##### `redirectTo(string $url): self`

Sets the redirect URL for after successful authentication.

**Parameters:**
- `$url` (string): The URL to redirect to

**Example:**
```php
$builder->redirectTo('/dashboard');
```

##### `context(array $data): self`

Adds contextual data to the magic link.

**Parameters:**
- `$data` (array): Associative array of context data

**Example:**
```php
$builder->context([
    'user_id' => 123,
    'source' => 'mobile_app',
    'remember' => true
]);
```

##### `expiresInMinutes(int $minutes): self`

Sets the expiration time in minutes.

**Parameters:**
- `$minutes` (int): Number of minutes until expiration

**Example:**
```php
$builder->expiresInMinutes(30);
```

##### `expiresInHours(int $hours): self`

Sets the expiration time in hours.

**Parameters:**
- `$hours` (int): Number of hours until expiration

**Example:**
```php
$builder->expiresInHours(2);
```

##### `expiresInDays(int $days): self`

Sets the expiration time in days.

**Parameters:**
- `$days` (int): Number of days until expiration

**Example:**
```php
$builder->expiresInDays(7);
```

##### `expiresAt(\DateTimeInterface $dateTime): self`

Sets the exact expiration date and time.

**Parameters:**
- `$dateTime` (DateTimeInterface): The expiration date and time

**Example:**
```php
$builder->expiresAt(now()->addHours(24));
```

##### `generate(): MagicLink`

Generates and saves the magic link.

**Returns:** `MagicLink` model instance

**Example:**
```php
$link = $builder->generate();
echo $link->getUrl(); // Full magic link URL
```

### MagicLinkConsumer

Consumer class for verifying and consuming magic links.

#### Methods

##### `isValid(): bool`

Checks if the magic link is valid (exists and not expired).

**Returns:** Boolean indicating validity

**Example:**
```php
if ($consumer->isValid()) {
    // Link is valid
}
```

##### `canUse(): bool`

Checks if the magic link can be consumed (not already used).

**Returns:** Boolean indicating if link can be used

**Example:**
```php
if ($consumer->canUse()) {
    // Link can be consumed
}
```

##### `getError(): ?string`

Gets the error message if link is invalid.

**Returns:** Error message or null

**Example:**
```php
$error = $consumer->getError();
if ($error) {
    echo "Error: " . $error;
}
```

##### `getLink(): ?MagicLink`

Gets the magic link model if valid.

**Returns:** `MagicLink` instance or null

**Example:**
```php
$link = $consumer->getLink();
if ($link) {
    echo $link->email;
}
```

##### `consume(?string $ipAddress = null, ?string $userAgent = null): ?MagicLink`

Consumes the magic link and marks it as used.

**Parameters:**
- `$ipAddress` (string, optional): IP address of the consumer
- `$userAgent` (string, optional): User agent of the consumer

**Returns:** `MagicLink` instance or null

**Example:**
```php
$link = $consumer->consume(
    request()->ip(),
    request()->userAgent()
);
```

### MagicLinkManager

Core service for managing magic links.

#### Methods

##### `create(string $email, ?string $redirectUrl = null, array $context = [], ?\DateTimeInterface $expiresAt = null): MagicLink`

Creates a new magic link.

**Parameters:**
- `$email` (string): Email address
- `$redirectUrl` (string, optional): Redirect URL
- `$context` (array, optional): Context data
- `$expiresAt` (DateTimeInterface, optional): Expiration time

**Returns:** `MagicLink` instance

##### `find(string $token): ?MagicLink`

Finds a magic link by token.

**Parameters:**
- `$token` (string): The magic link token

**Returns:** `MagicLink` instance or null

##### `verify(string $token): bool`

Verifies if a token is valid.

**Parameters:**
- `$token` (string): The magic link token

**Returns:** Boolean indicating validity

##### `extend(string $token, \DateTimeInterface $newExpiresAt): bool`

Extends the expiration time of a magic link.

**Parameters:**
- `$token` (string): The magic link token
- `$newExpiresAt` (DateTimeInterface): New expiration time

**Returns:** Boolean indicating success

##### `revoke(string $token): bool`

Revokes a magic link (marks as used).

**Parameters:**
- `$token` (string): The magic link token

**Returns:** Boolean indicating success

##### `cleanup(): int`

Removes expired magic links.

**Returns:** Number of deleted records

---

## Models

### MagicLink

Eloquent model representing a magic link.

#### Properties

- `id` (int): Primary key
- `email` (string): Email address
- `token` (string): Hashed token
- `redirect_url` (string, nullable): Redirect URL
- `context` (array): Context data (JSON)
- `expires_at` (Carbon): Expiration timestamp
- `consumed_at` (Carbon, nullable): Consumption timestamp
- `consumed_ip` (string, nullable): Consumer IP address
- `consumed_user_agent` (string, nullable): Consumer user agent
- `created_at` (Carbon): Creation timestamp
- `updated_at` (Carbon): Update timestamp

#### Methods

##### `getUrl(): string`

Gets the full magic link URL.

**Returns:** Complete magic link URL

**Example:**
```php
$url = $magicLink->getUrl();
// Returns: https://app.example.com/magic-link/abc123...
```

##### `isExpired(): bool`

Checks if the magic link has expired.

**Returns:** Boolean indicating expiration status

**Example:**
```php
if ($magicLink->isExpired()) {
    echo "Link has expired";
}
```

##### `isConsumed(): bool`

Checks if the magic link has been consumed.

**Returns:** Boolean indicating consumption status

**Example:**
```php
if ($magicLink->isConsumed()) {
    echo "Link has been used";
}
```

##### `canBeConsumed(): bool`

Checks if the magic link can be consumed (not expired and not consumed).

**Returns:** Boolean indicating if link can be consumed

**Example:**
```php
if ($magicLink->canBeConsumed()) {
    // Proceed with consumption
}
```

##### `consume(?string $ipAddress = null, ?string $userAgent = null): bool`

Marks the magic link as consumed.

**Parameters:**
- `$ipAddress` (string, optional): IP address
- `$userAgent` (string, optional): User agent

**Returns:** Boolean indicating success

**Example:**
```php
$success = $magicLink->consume(
    request()->ip(),
    request()->userAgent()
);
```

#### Scopes

##### `expired()`

Query scope for expired magic links.

**Example:**
```php
$expiredLinks = MagicLink::expired()->get();
```

##### `notExpired()`

Query scope for non-expired magic links.

**Example:**
```php
$validLinks = MagicLink::notExpired()->get();
```

##### `consumed()`

Query scope for consumed magic links.

**Example:**
```php
$usedLinks = MagicLink::consumed()->get();
```

##### `notConsumed()`

Query scope for non-consumed magic links.

**Example:**
```php
$availableLinks = MagicLink::notConsumed()->get();
```

##### `forEmail(string $email)`

Query scope for specific email address.

**Parameters:**
- `$email` (string): Email address to filter by

**Example:**
```php
$userLinks = MagicLink::forEmail('user@example.com')->get();
```

---

## Events

### MagicLinkGenerated

Fired when a new magic link is generated.

#### Properties

- `magicLink` (MagicLink): The generated magic link

#### Example Usage

```php
use Grazulex\OneClickLogin\Events\MagicLinkGenerated;

Event::listen(MagicLinkGenerated::class, function ($event) {
    $link = $event->magicLink;
    
    // Send email notification
    Mail::to($link->email)->send(new MagicLinkMail($link));
    
    // Log the generation
    Log::info('Magic link generated', [
        'email' => $link->email,
        'expires_at' => $link->expires_at,
    ]);
});
```

### MagicLinkConsumed

Fired when a magic link is successfully consumed.

#### Properties

- `magicLink` (MagicLink): The consumed magic link
- `ipAddress` (string, nullable): Consumer IP address
- `userAgent` (string, nullable): Consumer user agent

#### Example Usage

```php
use Grazulex\OneClickLogin\Events\MagicLinkConsumed;

Event::listen(MagicLinkConsumed::class, function ($event) {
    $link = $event->magicLink;
    
    // Log successful authentication
    Log::info('Magic link consumed', [
        'email' => $link->email,
        'ip_address' => $event->ipAddress,
        'user_agent' => $event->userAgent,
        'context' => $link->context,
    ]);
    
    // Update user last login
    $user = User::where('email', $link->email)->first();
    if ($user) {
        $user->update(['last_login_at' => now()]);
    }
});
```

### MagicLinkExpired

Fired when an attempt is made to consume an expired magic link.

#### Properties

- `magicLink` (MagicLink): The expired magic link
- `attemptedAt` (Carbon): When the consumption was attempted

#### Example Usage

```php
use Grazulex\OneClickLogin\Events\MagicLinkExpired;

Event::listen(MagicLinkExpired::class, function ($event) {
    $link = $event->magicLink;
    
    // Log security event
    Log::warning('Attempted to use expired magic link', [
        'email' => $link->email,
        'expired_at' => $link->expires_at,
        'attempted_at' => $event->attemptedAt,
    ]);
    
    // Optionally notify user of security event
    // SecurityNotification::send($link->email, 'expired_link_attempt');
});
```

---

## Console Commands

### `magic-link:cleanup`

Removes expired magic links from the database.

#### Usage

```bash
php artisan magic-link:cleanup
```

#### Options

- `--force`: Skip confirmation prompt
- `--older-than=DAYS`: Only remove links older than specified days (default: 0)

#### Examples

```bash
# Remove all expired links
php artisan magic-link:cleanup

# Remove without confirmation
php artisan magic-link:cleanup --force

# Remove links expired for more than 7 days
php artisan magic-link:cleanup --older-than=7
```

### `magic-link:prune`

Alias for the cleanup command.

#### Usage

```bash
php artisan magic-link:prune
```

### `magic-link:stats`

Displays statistics about magic links in the database.

#### Usage

```bash
php artisan magic-link:stats
```

#### Output Example

```
Magic Link Statistics
====================

Total Links: 1,234
Active Links: 45
Expired Links: 1,189
Consumed Links: 987
Success Rate: 80.0%

Recent Activity (Last 7 days):
- Generated: 156
- Consumed: 134
- Expired: 22
```

---

## Configuration

### Config File: `config/sharelink.php`

#### Available Options

```php
return [
    // Default expiration time in minutes
    'default_expiration_minutes' => 15,
    
    // URL generation
    'url' => [
        'route_name' => 'magic-link.consume',
        'signed' => true,
    ],
    
    // Database table name
    'table_name' => 'magic_links',
    
    // Token configuration
    'token' => [
        'length' => 64,
        'hash_algo' => 'sha256',
    ],
    
    // Cleanup configuration
    'cleanup' => [
        'enabled' => true,
        'older_than_days' => 7,
    ],
    
    // Rate limiting
    'rate_limiting' => [
        'enabled' => true,
        'max_attempts' => 3,
        'decay_minutes' => 5,
    ],
    
    // Security
    'security' => [
        'require_same_ip' => false,
        'require_same_user_agent' => false,
        'max_consumption_attempts' => 3,
    ],
];
```

#### Configuration Details

##### `default_expiration_minutes`

Default expiration time for magic links when not explicitly set.

**Type:** `int`  
**Default:** `15`

##### `url.route_name`

The route name used for generating magic link URLs.

**Type:** `string`  
**Default:** `'magic-link.consume'`

##### `url.signed`

Whether to generate signed URLs for additional security.

**Type:** `bool`  
**Default:** `true`

##### `table_name`

Database table name for storing magic links.

**Type:** `string`  
**Default:** `'magic_links'`

##### `token.length`

Length of the generated token before hashing.

**Type:** `int`  
**Default:** `64`

##### `token.hash_algo`

Algorithm used for hashing tokens.

**Type:** `string`  
**Default:** `'sha256'`

##### `cleanup.enabled`

Whether automatic cleanup is enabled.

**Type:** `bool`  
**Default:** `true`

##### `cleanup.older_than_days`

Number of days after expiration before links are eligible for cleanup.

**Type:** `int`  
**Default:** `7`

##### `rate_limiting.enabled`

Whether rate limiting is enabled for magic link requests.

**Type:** `bool`  
**Default:** `true`

##### `rate_limiting.max_attempts`

Maximum number of magic link requests per decay period.

**Type:** `int`  
**Default:** `3`

##### `rate_limiting.decay_minutes`

Time window for rate limiting in minutes.

**Type:** `int`  
**Default:** `5`

##### `security.require_same_ip`

Whether to require the same IP address for consumption as generation.

**Type:** `bool`  
**Default:** `false`

##### `security.require_same_user_agent`

Whether to require the same user agent for consumption as generation.

**Type:** `bool`  
**Default:** `false`

##### `security.max_consumption_attempts`

Maximum number of failed consumption attempts before blocking.

**Type:** `int`  
**Default:** `3`

---

## Error Handling

### Exception Classes

#### `MagicLinkException`

Base exception class for all magic link related errors.

#### `InvalidTokenException`

Thrown when an invalid token is provided.

#### `ExpiredLinkException`

Thrown when attempting to consume an expired magic link.

#### `ConsumedLinkException`

Thrown when attempting to consume an already used magic link.

#### `RateLimitException`

Thrown when rate limits are exceeded.

### Example Error Handling

```php
use Grazulex\OneClickLogin\Exceptions\MagicLinkException;
use Grazulex\OneClickLogin\Exceptions\ExpiredLinkException;
use Grazulex\OneClickLogin\Exceptions\ConsumedLinkException;

try {
    $consumer = OneClickLogin::consume($token);
    
    if (!$consumer->isValid()) {
        throw new MagicLinkException($consumer->getError());
    }
    
    $link = $consumer->consume(request()->ip(), request()->userAgent());
    
} catch (ExpiredLinkException $e) {
    return back()->withErrors(['token' => 'This magic link has expired.']);
    
} catch (ConsumedLinkException $e) {
    return back()->withErrors(['token' => 'This magic link has already been used.']);
    
} catch (MagicLinkException $e) {
    return back()->withErrors(['token' => 'Invalid magic link.']);
}
```

This API reference provides comprehensive documentation for all available methods, classes, and configuration options in the Laravel OneClickLogin package.
