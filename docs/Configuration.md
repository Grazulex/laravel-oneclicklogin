# Configuration

Laravel OneClickLogin provides extensive configuration options to customize the package behavior for your specific needs.

## Configuration File

The main configuration file is located at `config/oneclicklogin.php`. You can publish it using:

```bash
php artisan vendor:publish --provider="Grazulex\OneClickLogin\OneClickLoginServiceProvider" --tag="config"
```

## Available Configuration Options

### Basic Settings

```php
<?php

return [
    // Time-to-live for magic links in minutes
    'ttl_minutes' => env('ONECLICKLOGIN_TTL_MINUTES', 15),

    // Maximum number of uses per magic link
    'max_uses' => env('ONECLICKLOGIN_MAX_USES', 1),

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
];
```

### Security Configuration

```php
// Security features
'ip_binding' => env('ONECLICKLOGIN_IP_BINDING', false),
'device_binding' => env('ONECLICKLOGIN_DEVICE_BINDING', false),
'enable_otp_step_up' => env('ONECLICKLOGIN_ENABLE_OTP_STEP_UP', false),
'otp_provider' => env('ONECLICKLOGIN_OTP_PROVIDER', null),
```

### Multi-Persona Support

```php
// MultiPersona integration
'multi_persona' => [
    'enabled' => env('ONECLICKLOGIN_MULTI_PERSONA_ENABLED', true),
    'keys' => ['persona', 'tenant', 'role'],
],
```

### Redirect URLs

```php
// Redirect configurations
'redirect_after_login' => env('ONECLICKLOGIN_REDIRECT_AFTER_LOGIN', '/'),
'redirect_on_invalid' => env('ONECLICKLOGIN_REDIRECT_ON_INVALID', '/login?invalid=1'),
```

## Environment Variables

Add these variables to your `.env` file to customize the package:

```env
# Basic Configuration
ONECLICKLOGIN_TTL_MINUTES=15
ONECLICKLOGIN_MAX_USES=1
ONECLICKLOGIN_GUARD=web
ONECLICKLOGIN_USER_MODEL="App\Models\User"
ONECLICKLOGIN_EMAIL_FIELD=email
ONECLICKLOGIN_ALLOW_UNKNOWN_USERS=false
ONECLICKLOGIN_DEFAULT_REDIRECT_URL="/dashboard"

# Security
ONECLICKLOGIN_IP_BINDING=false
ONECLICKLOGIN_DEVICE_BINDING=false
ONECLICKLOGIN_ENABLE_OTP_STEP_UP=false

# Multi-Persona
ONECLICKLOGIN_MULTI_PERSONA_ENABLED=true

# Redirects
ONECLICKLOGIN_REDIRECT_AFTER_LOGIN="/"
ONECLICKLOGIN_REDIRECT_ON_INVALID="/login?invalid=1"
```

## Configuration Details

### TTL (Time To Live)

Controls how long magic links remain valid:

```php
'ttl_minutes' => 15, // Links expire after 15 minutes
```

**Recommended values:**
- **High security**: 5-10 minutes
- **Standard**: 15-30 minutes
- **User-friendly**: 60 minutes maximum

### User Model Configuration

Configure which user model and email field to use:

```php
'user_model' => App\Models\Customer::class,
'email_field' => 'email_address',
```

Your user model must:
- Extend `Illuminate\Foundation\Auth\User` or implement `Illuminate\Contracts\Auth\Authenticatable`
- Have an email field (configurable name)
- Be accessible via the configured guard

### Guard Configuration

Specify which authentication guard to use:

```php
'guard' => 'api', // For API authentication
'guard' => 'admin', // For admin panel
'guard' => 'web', // Default web guard
```

### Unknown Users Handling

Control what happens when a magic link is requested for an unknown email:

```php
// Redirect to registration
'allow_unknown_users' => true,

// Return error for unknown emails
'allow_unknown_users' => false,
```

When `allow_unknown_users` is `true`:
- Unknown users are redirected to registration
- Email and context are stored in session
- Can be used for invitation flows

### Security Features

#### IP Binding
```php
'ip_binding' => true, // Links only work from the same IP
```

#### Device Binding
```php
'device_binding' => true, // Links tied to device fingerprint
```

#### OTP Step-up Authentication
```php
'enable_otp_step_up' => true,
'otp_provider' => 'your-otp-service',
```

## Custom User Models

### Standard User Model

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];
}
```

### Custom User Model Example

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Customer extends Authenticatable
{
    protected $table = 'customers';
    
    protected $fillable = [
        'full_name',
        'email_address', // Custom email field
        'password',
        'company_id',
    ];
    
    // Configuration:
    // 'user_model' => App\Models\Customer::class,
    // 'email_field' => 'email_address',
}
```

### Multi-Tenant User Model

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TenantUser extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'tenant_id',
        'role',
    ];
    
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

## Validation Rules

The package includes built-in validation for configuration values:

### TTL Validation
- Must be between 1 and 1440 minutes (24 hours)
- Cannot be negative

### User Model Validation
- Must be a valid class name
- Class must exist and be instantiable
- Must implement Authenticatable contract

### Email Field Validation
- Must be a valid database column name
- Field must exist in the user model's table

### URL Validation
- Redirect URLs are validated for security
- Prevents open redirect vulnerabilities
- Supports relative and absolute URLs

## Runtime Configuration

You can modify configuration at runtime:

```php
// Temporarily change TTL
config(['oneclicklogin.ttl_minutes' => 60]);

// Use different user model for specific operation
config(['oneclicklogin.user_model' => App\Models\Admin::class]);

$link = OneClickLogin::for('admin@example.com')->generate();
```

## Configuration Testing

Test your configuration with the built-in command:

```bash
php artisan oneclicklogin:validate-config
```

This command checks:
- User model exists and is valid
- Email field exists in database
- TTL is within valid range
- URLs are properly formatted
- Guard exists in auth configuration

## Next Steps

- [Quick Start Guide](Quick-Start)
- [Generating Magic Links](Generating-Links)
- [Security Features](Security)
