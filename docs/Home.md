# Laravel OneClickLogin Documentation

> **🚀 Secure, Fast, and Configurable Magic Link Authentication for Laravel**

Laravel OneClickLogin is a modern, production-ready package that provides seamless magic link authentication for Laravel applications. Send secure, time-limited authentication links via email and authenticate users without passwords.

## 🌟 Key Features

- **🔐 Secure Magic Links** - Time-limited, single-use authentication tokens
- **⚙️ Highly Configurable** - Customizable user models, TTL, redirects, and more
- **🛡️ Rate Limiting** - Built-in protection against brute force attacks
- **📊 Observability** - Comprehensive logging and event system
- **🎯 Laravel Native** - Follows Laravel conventions and best practices
- **✅ 100% Tested** - Complete test coverage with PHPStan level 9 compliance
- **🔄 Multi-Persona Support** - Advanced user context management
- **📱 JSON API Ready** - Full support for SPA and mobile applications

## 📋 Requirements

- **PHP**: 8.3+
- **Laravel**: 11.0+ | 12.0+
- **Database**: MySQL, PostgreSQL, SQLite, or SQL Server

## 🚀 Quick Start

### Installation

```bash
composer require grazulex/laravel-oneclicklogin
```

### Configuration

```bash
php artisan vendor:publish --provider="Grazulex\OneClickLogin\OneClickLoginServiceProvider" --tag="config"
```

### Migration

```bash
php artisan migrate
```

### Basic Usage

```php
use Grazulex\OneClickLogin\Facades\OneClickLogin;

// Generate a magic link
$link = OneClickLogin::for('user@example.com')
    ->redirectTo('/dashboard')
    ->expiresInMinutes(15)
    ->generate();

// Get the magic link URL to send via your preferred method
$magicUrl = $link->getUrl();

// Or use it with your own mail/notification system
// Mail::to('user@example.com')->send(new YourMagicLinkMail($magicUrl));
// User::find(1)->notify(new YourMagicLinkNotification($magicUrl));
```

## 📚 Documentation Structure

### Getting Started
- [Installation & Setup](Installation)
- [Configuration](Configuration)
- [Quick Start Guide](Quick-Start)

### Core Features
- [Generating Magic Links](Generating-Links)
- [Consuming & Verification](Verification)
- [User Management](User-Management)
- [Security Features](Security)

### Advanced Usage
- [Rate Limiting](Rate-Limiting)
- [Events & Logging](Events-Logging)
- [Multi-Persona Support](Multi-Persona)
- [Console Commands](Console-Commands)

### Integration Examples
- [Basic Laravel App](Examples-Basic)
- [SPA with JSON API](Examples-SPA)
- [Multi-Tenant Application](Examples-Multi-Tenant)
- [E-commerce Platform](Examples-E-commerce)

### API Reference
- [Facade Methods](API-Facade)
- [Configuration Options](API-Configuration)
- [Events Reference](API-Events)
- [Middleware Reference](API-Middleware)

### Troubleshooting
- [Common Issues](Troubleshooting)
- [FAQ](FAQ)
- [Migration Guide](Migration)

## 🛠️ Development

### Testing

```bash
# Run all tests
vendor/bin/pest

# Run with coverage
vendor/bin/pest --coverage

# PHPStan analysis
vendor/bin/phpstan analyse
```

### Contributing

We welcome contributions! Please see our [Contributing Guide](Contributing) for details.

## 📄 License

Laravel OneClickLogin is open-sourced software licensed under the [MIT License](License).

## 🆘 Support

- **Issues**: [GitHub Issues](https://github.com/Grazulex/laravel-oneclicklogin/issues)
- **Discussions**: [GitHub Discussions](https://github.com/Grazulex/laravel-oneclicklogin/discussions)
- **Security**: See [Security Policy](Security-Policy)

---

**Made with ❤️ for the Laravel community**
