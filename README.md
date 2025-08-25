# Laravel OneClickLogin

[![Latest Version on Packagist](https://img.shields.io/packagist/v/grazulex/laravel-oneclicklogin.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-oneclicklogin)
[![Total Downloads](https://img.shields.io/packagist/dt/grazulex/laravel-oneclicklogin.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-oneclicklogin)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/grazulex/laravel-oneclicklogin/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/grazulex/laravel-oneclicklogin/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/grazulex/laravel-oneclicklogin/code-quality.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/grazulex/laravel-oneclicklogin/actions?query=workflow%3Acode-quality+branch%3Amain)

Passwordless authentication for Laravel applications via "magic links" - secure, single-use, time-limited URLs that provide seamless user login without passwords.

## Features

- 🔐 **Passwordless Authentication** - Replace or complement password-based login
- 🔒 **Security-by-Default** - Signed, hashed tokens with short expirations and single-use
- ⚡ **Developer Experience** - Fluent API, Facade, Artisan commands, events, and test helpers
- 🎭 **MultiPersona Integration** - Include persona/tenant/role context in magic links
- 📊 **ShareLink Integration** - Optional delivery layer with audit trails and analytics
- 🚀 **Laravel Native** - Integrates seamlessly with Auth, Notifications, and Middleware

## Installation

You can install the package via composer:

```bash
composer require grazulex/laravel-oneclicklogin
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="oneclicklogin-migrations"
php artisan migrate
```

Optionally, you can publish the config file:

```bash
php artisan vendor:publish --tag="oneclicklogin-config"
```

## Usage

### Basic Magic Link Creation

```php
use Grazulex\OneClickLogin\Facades\OneClickLogin;

// Send a magic link via email
OneClickLogin::to($user)
    ->via('mail')
    ->expireIn(15) // minutes
    ->withContext([
        'redirect' => '/dashboard',
        'remember' => true
    ])
    ->send();
```

### Advanced Usage with MultiPersona

```php
OneClickLogin::to($user)
    ->via('mail')
    ->expireIn(30)
    ->maxUses(1)
    ->withContext([
        'persona' => 'client',
        'tenant'  => 123,
        'role'    => 'admin',
        'redirect'=> '/admin/dashboard',
        'remember'=> true
    ])
    ->bindIp() // Optional IP binding
    ->bindDevice($request) // Optional device binding
    ->send();
```

### Creating Without Sending

```php
$link = OneClickLogin::create($user, options: [
    'ttl' => 30,
    'context' => ['redirect' => '/billing'],
]);

// Get the magic URL
$magicUrl = $link->getSignedUrl();
```

### Artisan Commands

```bash
# Send a magic link
php artisan oneclicklogin:send user@example.com --via=mail --ttl=15

# Revoke a magic link
php artisan oneclicklogin:revoke {link-id}

# Clean up expired links
php artisan oneclicklogin:prune

# Test magic link generation
php artisan oneclicklogin:test user@example.com
```

## Configuration

The package comes with sensible defaults, but you can customize everything:

```php
// config/oneclicklogin.php
return [
    'ttl_minutes' => 15,
    'max_uses' => 1,
    'guard' => 'web',
    'ip_binding' => false,
    'device_binding' => false,
    'enable_otp_step_up' => false,
    
    'multi_persona' => [
        'enabled' => true,
        'keys' => ['persona', 'tenant', 'role'],
    ],
    
    'rate_limit' => [
        'issue_per_email_per_hour' => 5,
        'consume_per_ip_per_min' => 20,
    ],
    
    // ... more options
];
```

## Security Features

- **Token Hashing** - Raw tokens are never stored; only SHA-256 hashes
- **Short TTL** - Default 15-minute expiration
- **Single Use** - Links are revoked after first successful use
- **Rate Limiting** - Built-in protection against abuse
- **IP/Device Binding** - Optional additional security layers
- **Signed URLs** - Protection against URL tampering
- **OTP Step-up** - Optional second factor for suspicious access

## Events

The package emits several events for observability:

- `MagicLinkCreated` - When a magic link is created
- `MagicLinkSent` - When a magic link is sent via notification
- `MagicLinkUsed` - When a magic link is successfully used
- `MagicLinkExpired` - When a magic link expires
- `MagicLinkRevoked` - When a magic link is revoked

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jean-Marc Strauven](https://github.com/grazulex)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
