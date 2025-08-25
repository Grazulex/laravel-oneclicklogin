# Quick Start Guide

This guide will get you up and running with Laravel OneClickLogin in just a few minutes.

## Prerequisites

- Laravel 11+ project
- User authentication system in place
- Mail configuration (for sending magic links)

## Step 1: Installation

```bash
composer require grazulex/laravel-oneclicklogin
php artisan migrate
```

## Step 2: Basic Implementation

### Create a Magic Link Request Form

```html
<!-- resources/views/auth/magic-link.blade.php -->
<form method="POST" action="{{ route('magic-link.request') }}">
    @csrf
    <div>
        <label for="email">Email Address</label>
        <input type="email" name="email" required>
    </div>
    <button type="submit">Send Magic Link</button>
</form>
```

### Handle Magic Link Requests

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Grazulex\OneClickLogin\Facades\OneClickLogin;
use Illuminate\Support\Facades\Mail;
use App\Mail\MagicLinkMail;

class MagicLinkController extends Controller
{
    public function showRequestForm()
    {
        return view('auth.magic-link');
    }
    
    public function sendMagicLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);
        
        try {
            // Generate magic link
            $link = OneClickLogin::for($request->email)
                ->redirectTo('/dashboard')
                ->expiresInMinutes(15)
                ->generate();
            
            // Send email with your mail system
            Mail::to($request->email)->send(new MagicLinkMail($link->getUrl()));
            
            return back()->with('success', 'Magic link sent to your email!');
            
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send magic link. Please try again.');
        }
    }
}
```

### Create Magic Link Mail

```php
<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use SerializesModels;
    
    public $magicUrl;
    
    public function __construct(string $magicUrl)
    {
        $this->magicUrl = $magicUrl;
    }
    
    public function build()
    {
        return $this->subject('Your Login Link')
                    ->view('emails.magic-link');
    }
}
```

### Create Email Template

```html
<!-- resources/views/emails/magic-link.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Your Login Link</title>
</head>
<body>
    <h1>Login to {{ config('app.name') }}</h1>
    
    <p>Click the button below to securely log in to your account:</p>
    
    <a href="{{ $magicUrl }}" 
       style="display: inline-block; padding: 12px 24px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;">
        Log In Now
    </a>
    
    <p>Or copy and paste this URL into your browser:</p>
    <p>{{ $magicUrl }}</p>
    
    <p><small>This link expires in 15 minutes and can only be used once.</small></p>
</body>
</html>
```

### Add Routes

```php
// routes/web.php
use App\Http\Controllers\Auth\MagicLinkController;

Route::get('/auth/magic-link', [MagicLinkController::class, 'showRequestForm'])
    ->name('magic-link.form');
    
Route::post('/auth/magic-link', [MagicLinkController::class, 'sendMagicLink'])
    ->name('magic-link.request');
```

## Step 3: Test Your Implementation

1. Visit `/auth/magic-link`
2. Enter your email address
3. Check your email for the magic link
4. Click the link to authenticate

## Real-World Examples

### Example 1: E-commerce Quick Login

```php
// In your checkout controller
public function expressCheckout(Request $request)
{
    $email = $request->input('email');
    
    // Generate magic link for quick checkout
    $link = OneClickLogin::for($email)
        ->redirectTo('/checkout/confirm')
        ->context(['cart_id' => session('cart_id')])
        ->expiresInMinutes(10) // Short expiry for checkout
        ->generate();
    
    // Send SMS or email
    $this->sendCheckoutLink($email, $link->getUrl());
    
    return response()->json([
        'message' => 'Quick login link sent!',
        'expires_in' => 10
    ]);
}
```

### Example 2: Admin Panel Access

```php
// Configure for admin users
config(['oneclicklogin.user_model' => App\Models\Admin::class]);
config(['oneclicklogin.guard' => 'admin']);

$link = OneClickLogin::for('admin@company.com')
    ->redirectTo('/admin/dashboard')
    ->expiresInMinutes(5) // Short expiry for admin access
    ->generate();
```

### Example 3: Mobile App Integration

```php
// API endpoint for mobile apps
Route::post('/api/auth/magic-link', function (Request $request) {
    $request->validate(['email' => 'required|email']);
    
    $link = OneClickLogin::for($request->email)
        ->redirectTo('/mobile/auth-success')
        ->expiresInMinutes(30)
        ->generate();
    
    // Return link for mobile app to handle
    return response()->json([
        'magic_url' => $link->getUrl(),
        'expires_at' => $link->expires_at,
        'token' => $link->token, // For deep linking
    ]);
});
```

### Example 4: Newsletter Subscription Confirmation

```php
public function confirmSubscription($email)
{
    // Create magic link for subscription confirmation
    $link = OneClickLogin::for($email)
        ->redirectTo('/newsletter/welcome')
        ->context(['action' => 'subscribe', 'list' => 'newsletter'])
        ->expiresInMinutes(60)
        ->generate();
    
    Mail::to($email)->send(new SubscriptionConfirmationMail($link->getUrl()));
}
```

### Example 5: Password Reset Alternative

```php
public function sendPasswordlessReset(Request $request)
{
    $user = User::where('email', $request->email)->first();
    
    if ($user) {
        $link = OneClickLogin::for($user->email)
            ->redirectTo('/profile/settings')
            ->context(['action' => 'password_reset'])
            ->expiresInMinutes(30)
            ->generate();
        
        Mail::to($user)->send(new PasswordlessResetMail($link->getUrl()));
    }
    
    // Always return success for security
    return back()->with('success', 'If an account exists, a reset link has been sent.');
}
```

## Using Console Commands

### Generate Magic Links via CLI

```bash
# Generate a magic link
php artisan oneclicklogin:generate user@example.com --url=/dashboard --ttl=30

# List all magic links
php artisan oneclicklogin:list

# List magic links for specific email
php artisan oneclicklogin:list --email=user@example.com

# Test magic link functionality
php artisan oneclicklogin:test --email=test@example.com

# Clean up expired links
php artisan oneclicklogin:prune
```

## JSON API Usage

The package supports JSON responses for API usage:

```javascript
// Frontend JavaScript
fetch('/magic-link/verify/abc123token', {
    headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    }
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        // Redirect to dashboard
        window.location.href = data.redirect_url;
    } else {
        // Show error message
        alert(data.message);
    }
});
```

Response format:
```json
{
    "success": true,
    "message": "Successfully authenticated",
    "redirect_url": "/dashboard"
}
```

## Error Handling

```php
use Grazulex\OneClickLogin\Exceptions\MagicLinkExpiredException;
use Grazulex\OneClickLogin\Exceptions\MagicLinkUsedException;

try {
    $link = OneClickLogin::for($email)->generate();
} catch (MagicLinkExpiredException $e) {
    // Handle expired link
    return back()->with('error', 'Magic link has expired');
} catch (MagicLinkUsedException $e) {
    // Handle already used link
    return back()->with('error', 'Magic link has already been used');
} catch (\Exception $e) {
    // Handle general errors
    return back()->with('error', 'Something went wrong');
}
```

## Security Best Practices

1. **Always use HTTPS** in production
2. **Set appropriate TTL** (5-30 minutes recommended)
3. **Validate email addresses** before generating links
4. **Log authentication attempts** for security monitoring
5. **Implement rate limiting** on magic link requests
6. **Use secure email delivery** methods

## Next Steps

- [Configuration Options](Configuration)
- [Security Features](Security)
- [Advanced Usage Examples](Examples-Advanced)
- [API Reference](API-Facade)
