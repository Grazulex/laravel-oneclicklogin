# Installation & Setup

## System Requirements

Before installing Laravel OneClickLogin, ensure your system meets these requirements:

- **PHP**: 8.3 or higher
- **Laravel**: 11.0+ or 12.0+
- **Database**: MySQL 5.7+, PostgreSQL 10+, SQLite 3.8+, or SQL Server 2017+
- **Extensions**: BCMath, OpenSSL, PDO, Mbstring, Tokenizer, XML

## Installation Steps

### 1. Install via Composer

```bash
composer require grazulex/laravel-oneclicklogin
```

### 2. Publish Configuration (Optional)

The package auto-discovers in Laravel 11+, but you can publish the configuration file:

```bash
php artisan vendor:publish --provider="Grazulex\OneClickLogin\OneClickLoginServiceProvider" --tag="config"
```

This creates `config/oneclicklogin.php` with all available options.

### 3. Run Migrations

```bash
php artisan migrate
```

This creates the `magic_links` table with the following structure:

```sql
CREATE TABLE magic_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    redirect_url VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    context JSON NULL,
    meta JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_token_hash (token_hash),
    INDEX idx_email (email),
    INDEX idx_expires_at (expires_at)
);
```

### 4. Environment Configuration

Add these environment variables to your `.env` file:

```env
# Magic Link Configuration
ONECLICKLOGIN_TTL_MINUTES=15
ONECLICKLOGIN_MAX_USES=1
ONECLICKLOGIN_GUARD=web
ONECLICKLOGIN_USER_MODEL="App\Models\User"
ONECLICKLOGIN_EMAIL_FIELD=email
ONECLICKLOGIN_ALLOW_UNKNOWN_USERS=false
ONECLICKLOGIN_DEFAULT_REDIRECT_URL="/dashboard"

# Security Features
ONECLICKLOGIN_IP_BINDING=false
ONECLICKLOGIN_DEVICE_BINDING=false
ONECLICKLOGIN_ENABLE_OTP_STEP_UP=false

# Rate Limiting
ONECLICKLOGIN_RATE_LIMIT_ATTEMPTS=10
ONECLICKLOGIN_RATE_LIMIT_MINUTES=5
```

## Verification

### Test Installation

Run the built-in test command to verify your installation:

```bash
php artisan oneclicklogin:test --email=test@example.com
```

Expected output:
```
Testing OneClickLogin Magic Link functionality...
1. Generating magic link...
✓ Magic link generated: abcd1234...
2. Testing validation...
✓ Magic link is valid
3. Testing consumption...
✓ Magic link consumed successfully
🎉 All tests completed successfully!
```

### Generate Your First Magic Link

```bash
php artisan oneclicklogin:generate user@example.com --url=/dashboard --ttl=30
```

## Laravel Integration

### Service Provider Registration

The package auto-registers its service provider in Laravel 11+. For older versions, add to `config/app.php`:

```php
'providers' => [
    // ...
    Grazulex\OneClickLogin\OneClickLoginServiceProvider::class,
],
```

### Facade Registration

Add the facade to `config/app.php` (auto-discovered in Laravel 11+):

```php
'aliases' => [
    // ...
    'OneClickLogin' => Grazulex\OneClickLogin\Facades\OneClickLogin::class,
],
```

## User Model Setup

### Default Configuration

By default, the package works with `App\Models\User`. Ensure your User model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
    ];
    
    // OneClickLogin will use the 'email' field by default
}
```

### Custom User Model

To use a custom user model, update your configuration:

```php
// config/oneclicklogin.php
return [
    'user_model' => App\Models\Customer::class,
    'email_field' => 'email_address', // Custom email field
    // ...
];
```

Your custom model must be authenticatable:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Customer extends Authenticatable
{
    protected $fillable = [
        'name',
        'email_address', // Custom email field
        'password',
    ];
    
    protected $table = 'customers';
}
```

## Route Configuration

### Default Routes

The package automatically registers these routes:

```
GET /magic-link/verify/{token}
```

### Custom Route Configuration

To customize routes, publish the configuration and modify:

```php
// config/oneclicklogin.php
return [
    'route_prefix' => 'auth/magic',
    'route_name' => 'auth.magic.',
    'route_middleware' => ['web', 'throttle:60,1'],
    // ...
];
```

## Next Steps

- [Configuration Options](Configuration)
- [Quick Start Guide](Quick-Start)
- [Generating Magic Links](Generating-Links)
