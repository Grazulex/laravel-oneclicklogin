# Laravel OneClickLogin

<div align="center">
  <img src="https://raw.githubusercontent.com/Grazulex/laravel-oneclicklogin/main/new_logo.png" alt="Laravel OneClickLogin" width="200">
  
  **Passwordless authentication via magic links for Laravel applications - secure, single-use, time-limited URLs for seamless user login.**
  
  *A powerful Laravel package for creating passwordless authentication with comprehensive security features and audit trails.*

[![Latest Version](https://img.shields.io/packagist/v/grazulex/laravel-oneclicklogin.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-oneclicklogin) [![Total Downloads](https://img.shields.io/packagist/dt/grazulex/laravel-oneclicklogin.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-oneclicklogin) [![License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://github.com/Grazulex/laravel-oneclicklogin/blob/main/LICENSE.md) [![PHP Version](https://img.shields.io/badge/php-8.3%2B-blue.svg?style=flat-square)](https://php.net/) [![Laravel Version](https://img.shields.io/badge/laravel-11.0%2B%20%7C%2012.0%2B-red.svg?style=flat-square)](https://laravel.com/) [![Tests](https://img.shields.io/github/actions/workflow/status/grazulex/laravel-oneclicklogin/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/grazulex/laravel-oneclicklogin/actions) [![Code Style](https://img.shields.io/badge/code%20style-pint-orange.svg?style=flat-square)](https://github.com/laravel/pint)

</div>

---

## 🚀 Overview

Laravel OneClickLogin is a comprehensive package for implementing passwordless authentication in your Laravel applications. Perfect for creating secure, time-limited magic links that provide seamless user login without passwords, with complete audit trails and advanced security features.

## ✨ Key Features

- 🔐 **Passwordless Authentication** - Replace or complement password-based login
- ⏰ **Time-Limited Access** - Set expiration dates and usage limits  
- 🔒 **Security-by-Default** - Signed, hashed tokens with short expirations
- 🚫 **Rate Limiting** - Per-email and per-IP rate limiting to prevent abuse
- 🌐 **IP & Device Binding** - Optional IP address and device fingerprint binding
- 🔏 **Signed URLs** - Laravel signed route integration for additional security
- 🔥 **Single-Use Links** - Magic links that expire after first successful use
- 📊 **Comprehensive Auditing** - Track access patterns, IPs, and timestamps
- 🛡️ **Advanced Security** - OTP step-up authentication for suspicious devices
- 🎭 **MultiPersona Integration** - Include persona/tenant/role context in links
- 📧 **Flexible Delivery** - Support for email, SMS, and custom notification channels
- 📋 **Management API** - Revoke and extend links programmatically
- 🎨 **CLI Commands** - Full Artisan command support
- � **Observability** - Built-in logging and metrics integration
- 🔗 **ShareLink Integration** - Optional delivery layer with analytics and audit trails
- 🧪 **Test-Friendly** - Comprehensive test coverage with easy mocking

## 📦 Installation

Install the package via Composer:

```bash
composer require grazulex/laravel-oneclicklogin
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="oneclicklogin-migrations"
php artisan migrate
```

Optionally, publish the configuration file:

```bash
php artisan vendor:publish --tag="oneclicklogin-config"
```

> 💡 **Auto-Discovery**: The service provider will be automatically registered thanks to Laravel's package auto-discovery.

## ⚡ Quick Start

> 📖 **Need more examples?** Check out our [Examples Gallery](https://github.com/Grazulex/laravel-oneclicklogin/wiki/Examples-SPA) for e-commerce, SPA, and multi-tenant scenarios.

### 🚀 Basic Usage

```php
use Grazulex\OneClickLogin\Facades\OneClickLogin;

// Send a magic link with expiration
$link = OneClickLogin::to($user)
    ->via('mail')
    ->expireIn(15) // 15 minutes
    ->withContext(['redirect' => '/dashboard'])
    ->send();

echo $link->getSignedUrl(); // https://yourapp.com/login/magic?token=abc123xyz
```

### 📧 Email Magic Links

```php
// Send via email with custom context
OneClickLogin::to($user)
    ->via('mail')
    ->expireIn(30) // 30 minutes
    ->maxUses(1)
    ->withContext([
        'redirect' => '/profile',
        'remember' => true
    ])
    ->send();
```

### 📱 SMS Magic Links

```php
// Send via SMS
OneClickLogin::to($user)
    ->via('sms')
    ->expireIn(10) // 10 minutes
    ->withContext(['redirect' => '/mobile-dashboard'])
    ->send();
```

### 🎭 MultiPersona Integration

```php
// Magic link with persona context
OneClickLogin::to($user)
    ->via('mail')
    ->expireIn(30)
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

### 🔥 Advanced Security Features

```php
// Secure magic link with IP restrictions and OTP step-up
OneClickLogin::to($user)
    ->via('mail')
    ->expireIn(15)
    ->bindIp() // Bind to current IP
    ->bindDevice($request) // Bind to device fingerprint
    ->withContext([
        'redirect' => '/secure-area',
        'otp_required' => true // Require OTP for suspicious access
    ])
    ->send();

// Create without sending for custom delivery
$link = OneClickLogin::create($user, [
    'ttl' => 30,
    'context' => ['redirect' => '/billing'],
]);
```

## 🔧 Requirements

• PHP 8.3+
• Laravel 11.0+ | 12.0+

> 📋 **Compatibility Matrix**: See our [Installation Guide](https://github.com/Grazulex/laravel-oneclicklogin/wiki/Installation) for detailed Laravel/PHP compatibility.

## 📚 Complete Documentation

For comprehensive documentation, examples, and advanced usage guides, visit our Wiki:

### 📖 [👉 Laravel OneClickLogin Wiki](https://github.com/Grazulex/laravel-oneclicklogin/wiki)

The wiki includes:

- 🚀 [Installation & Setup](https://github.com/Grazulex/laravel-oneclicklogin/wiki/Installation)
- ⚙️ [Configuration](https://github.com/Grazulex/laravel-oneclicklogin/wiki/Configuration)
- 🎯 [Quick Start Guide](https://github.com/Grazulex/laravel-oneclicklogin/wiki/Quick-Start)
- 🔗 [Link Creation Options](https://github.com/Grazulex/laravel-oneclicklogin/wiki/Link-Creation-Options)
- 📋 [API Reference](https://github.com/Grazulex/laravel-oneclicklogin/wiki/API-Reference)
- ⌨️ [Console Commands](https://github.com/Grazulex/laravel-oneclicklogin/wiki/Console-Commands)
- � [Examples](https://github.com/Grazulex/laravel-oneclicklogin/wiki/Examples-SPA)
- 🔧 [Troubleshooting](https://github.com/Grazulex/laravel-oneclicklogin/wiki/Troubleshooting)
- ❓ [FAQ](https://github.com/Grazulex/laravel-oneclicklogin/wiki/FAQ)

## 🎨 Artisan Commands

Laravel OneClickLogin includes powerful CLI commands for managing your magic links:

```bash
# Send a magic link
php artisan oneclicklogin:send user@example.com --via=mail --ttl=15

# List all magic links
php artisan oneclicklogin:list --active --expired

# Revoke a specific link
php artisan oneclicklogin:revoke abc123xyz

# Clean up expired links
php artisan oneclicklogin:prune --days=7

# Test magic link generation
php artisan oneclicklogin:test user@example.com
```

## 🔧 Configuration

The package comes with sensible defaults, but you can customize everything:

```php
// config/oneclicklogin.php
return [
    'ttl_minutes' => 15,
    'max_uses' => 1,
    'guard' => 'web',
    
    'security' => [
        'ip_binding' => false,
        'device_binding' => false,
        'enable_otp_step_up' => false,
        'hash_algorithm' => 'sha256',
        'signed_urls' => true,
    ],
    
    'rate_limit' => [
        'issue_per_email_per_hour' => 5,
        'consume_per_ip_per_min' => 20,
    ],
    
    'multi_persona' => [
        'enabled' => true,
        'keys' => ['persona', 'tenant', 'role'],
    ],
];
```

## 🔧 Troubleshooting

### Common Issue: API vs CLI Discrepancy

If `OneClickLogin::for()->generate()` fails but CLI commands work, this is typically an **environment setup issue**, not a package bug:

```bash
# Quick fix - ensure clean environment
php artisan migrate:fresh
php artisan cache:clear
php artisan config:clear

# Then test
php artisan tinker
>>> OneClickLogin::for('test@example.com')->generate();
```

**For testing, always use `RefreshDatabase`:**
```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class YourTest extends TestCase {
    use RefreshDatabase; // ← Prevents environment issues
}
```

👉 **Full troubleshooting guide**: [Wiki Troubleshooting](https://github.com/Grazulex/laravel-oneclicklogin/wiki/Troubleshooting)

## 🧪 Testing

```bash
composer test
```

## 🤝 Contributing

Please see the [Contributing Guide](CONTRIBUTING.md) for details.

## 🔒 Security

If you discover any security-related issues, please email [jms@grazulex.be](mailto:jms@grazulex.be) instead of using the issue tracker.

## 📝 Changelog

Please see the [Changelog](https://github.com/Grazulex/laravel-oneclicklogin/wiki/Home) for more information on what has changed recently.

## 📄 License

The MIT License (MIT). Please see [License File](https://github.com/Grazulex/laravel-oneclicklogin/blob/main/LICENSE.md) for more information.

## 👥 Credits

• [Jean-Marc Strauven](https://github.com/Grazulex)
• [All Contributors](https://github.com/Grazulex/laravel-oneclicklogin/contributors)

## 💬 Support

• 🐛 [Report Issues](https://github.com/Grazulex/laravel-oneclicklogin/issues)
• 💬 [Discussions](https://github.com/Grazulex/laravel-oneclicklogin/discussions)
• 📖 [Documentation](https://github.com/Grazulex/laravel-oneclicklogin/wiki)

---

**Laravel OneClickLogin** - Passwordless authentication for Laravel applications with comprehensive security features and audit trails.
