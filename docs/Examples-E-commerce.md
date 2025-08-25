# Real-World Example: E-commerce Platform

This comprehensive example shows how to integrate Laravel OneClickLogin into a complete e-commerce platform with multiple authentication flows.

## Project Structure

Our e-commerce platform has these user types:
- **Customers** - Regular shoppers
- **Vendors** - Store owners who sell products
- **Admins** - Platform administrators

## Database Setup

### User Models

```php
<?php
// app/Models/Customer.php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Customer extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'email_verified_at',
    ];
    
    protected $guard = 'customer';
    
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    
    public function cart()
    {
        return $this->hasOne(Cart::class);
    }
}
```

```php
<?php
// app/Models/Vendor.php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Vendor extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'company_name',
        'store_slug',
        'is_verified',
    ];
    
    protected $guard = 'vendor';
    
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    
    public function store()
    {
        return $this->hasOne(Store::class);
    }
}
```

### Guards Configuration

```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'customer' => [
        'driver' => 'session',
        'provider' => 'customers',
    ],
    'vendor' => [
        'driver' => 'session',
        'provider' => 'vendors',
    ],
    'admin' => [
        'driver' => 'session',
        'provider' => 'admins',
    ],
],

'providers' => [
    'customers' => [
        'driver' => 'eloquent',
        'model' => App\Models\Customer::class,
    ],
    'vendors' => [
        'driver' => 'eloquent',
        'model' => App\Models\Vendor::class,
    ],
    'admins' => [
        'driver' => 'eloquent',
        'model' => App\Models\Admin::class,
    ],
],
```

## Magic Link Service

Create a centralized service for handling different user types:

```php
<?php
// app/Services/MagicLinkService.php
namespace App\Services;

use Grazulex\OneClickLogin\Facades\OneClickLogin;
use Illuminate\Support\Facades\Mail;
use App\Mail\CustomerMagicLinkMail;
use App\Mail\VendorMagicLinkMail;
use App\Mail\AdminMagicLinkMail;

class MagicLinkService
{
    public function sendCustomerMagicLink(string $email, array $context = [])
    {
        // Configure for customers
        config([
            'oneclicklogin.user_model' => \App\Models\Customer::class,
            'oneclicklogin.guard' => 'customer',
        ]);
        
        $redirectTo = $context['redirect_to'] ?? '/customer/dashboard';
        $ttl = $context['ttl'] ?? 15;
        
        $link = OneClickLogin::for($email)
            ->redirectTo($redirectTo)
            ->context($context)
            ->expiresInMinutes($ttl)
            ->generate();
        
        Mail::to($email)->send(new CustomerMagicLinkMail($link, $context));
        
        return $link;
    }
    
    public function sendVendorMagicLink(string $email, array $context = [])
    {
        // Configure for vendors
        config([
            'oneclicklogin.user_model' => \App\Models\Vendor::class,
            'oneclicklogin.guard' => 'vendor',
        ]);
        
        $redirectTo = $context['redirect_to'] ?? '/vendor/dashboard';
        $ttl = $context['ttl'] ?? 30; // Longer session for vendors
        
        $link = OneClickLogin::for($email)
            ->redirectTo($redirectTo)
            ->context($context)
            ->expiresInMinutes($ttl)
            ->generate();
        
        Mail::to($email)->send(new VendorMagicLinkMail($link, $context));
        
        return $link;
    }
    
    public function sendAdminMagicLink(string $email, array $context = [])
    {
        // Configure for admins
        config([
            'oneclicklogin.user_model' => \App\Models\Admin::class,
            'oneclicklogin.guard' => 'admin',
        ]);
        
        $redirectTo = $context['redirect_to'] ?? '/admin/dashboard';
        $ttl = $context['ttl'] ?? 5; // Short session for admin access
        
        $link = OneClickLogin::for($email)
            ->redirectTo($redirectTo)
            ->context($context)
            ->expiresInMinutes($ttl)
            ->generate();
        
        Mail::to($email)->send(new AdminMagicLinkMail($link, $context));
        
        return $link;
    }
}
```

## Controllers

### Customer Authentication

```php
<?php
// app/Http/Controllers/Customer/AuthController.php
namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private MagicLinkService $magicLinkService)
    {
    }
    
    public function showLoginForm()
    {
        return view('customer.auth.login');
    }
    
    public function sendMagicLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:customers,email'
        ]);
        
        $context = [
            'type' => 'login',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
        
        $this->magicLinkService->sendCustomerMagicLink(
            $request->email,
            $context
        );
        
        return back()->with('success', 'Magic link sent to your email!');
    }
    
    // Quick checkout magic link
    public function sendCheckoutLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'cart_id' => 'required|exists:carts,id'
        ]);
        
        $context = [
            'type' => 'checkout',
            'cart_id' => $request->cart_id,
            'redirect_to' => '/checkout/review',
            'ttl' => 10, // Short expiry for checkout
        ];
        
        $this->magicLinkService->sendCustomerMagicLink(
            $request->email,
            $context
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Quick checkout link sent!',
            'expires_in' => 10
        ]);
    }
}
```

### Vendor Authentication

```php
<?php
// app/Http/Controllers/Vendor/AuthController.php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private MagicLinkService $magicLinkService)
    {
    }
    
    public function showLoginForm()
    {
        return view('vendor.auth.login');
    }
    
    public function sendMagicLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:vendors,email'
        ]);
        
        $context = [
            'type' => 'vendor_login',
            'redirect_to' => '/vendor/dashboard',
        ];
        
        $this->magicLinkService->sendVendorMagicLink(
            $request->email,
            $context
        );
        
        return back()->with('success', 'Magic link sent to your email!');
    }
    
    // Store management quick access
    public function sendStoreAccessLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:vendors,email',
            'store_section' => 'required|in:products,orders,analytics'
        ]);
        
        $section = $request->store_section;
        $context = [
            'type' => 'store_access',
            'section' => $section,
            'redirect_to' => "/vendor/{$section}",
            'ttl' => 60, // Longer session for store management
        ];
        
        $this->magicLinkService->sendVendorMagicLink(
            $request->email,
            $context
        );
        
        return response()->json([
            'success' => true,
            'message' => "Quick access link for {$section} sent!"
        ]);
    }
}
```

## Mail Templates

### Customer Magic Link Email

```php
<?php
// app/Mail/CustomerMagicLinkMail.php
namespace App\Mail;

use Illuminate\Mail\Mailable;
use Grazulex\OneClickLogin\Models\MagicLink;

class CustomerMagicLinkMail extends Mailable
{
    public function __construct(
        public MagicLink $magicLink,
        public array $context = []
    ) {}
    
    public function build()
    {
        $subject = match($this->context['type'] ?? 'login') {
            'checkout' => 'Complete Your Purchase - Quick Access',
            'login' => 'Your Login Link',
            default => 'Access Your Account'
        };
        
        return $this->subject($subject)
                    ->view('emails.customer.magic-link')
                    ->with([
                        'magicUrl' => $this->magicLink->getUrl(),
                        'expiresAt' => $this->magicLink->expires_at,
                        'context' => $this->context,
                    ]);
    }
}
```

### Customer Email Template

```html
<!-- resources/views/emails/customer/magic-link.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>{{ config('app.name') }} - Access Link</title>
    <style>
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #e3342f;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            font-family: Arial, sans-serif;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ config('app.name') }}</h1>
        
        @if($context['type'] === 'checkout')
            <h2>Complete Your Purchase</h2>
            <p>You're just one click away from completing your order!</p>
            
            <a href="{{ $magicUrl }}" class="btn">Complete Purchase</a>
            
            <p><strong>Cart ID:</strong> #{{ $context['cart_id'] }}</p>
            <p><small>This link expires in {{ $context['ttl'] }} minutes.</small></p>
            
        @elseif($context['type'] === 'login')
            <h2>Your Login Link</h2>
            <p>Click the button below to securely access your account:</p>
            
            <a href="{{ $magicUrl }}" class="btn">Log In to Your Account</a>
            
            <p><small>This link expires at {{ $expiresAt->format('M j, Y g:i A') }}.</small></p>
        @endif
        
        <hr>
        
        <p>Or copy and paste this URL:</p>
        <p style="word-break: break-all;">{{ $magicUrl }}</p>
        
        <p><small>
            If you didn't request this link, please ignore this email.<br>
            This link can only be used once and expires automatically.
        </small></p>
    </div>
</body>
</html>
```

### Vendor Email Template

```html
<!-- resources/views/emails/vendor/magic-link.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>{{ config('app.name') }} - Vendor Access</title>
    <style>
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #38c172;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            font-family: Arial, sans-serif;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ config('app.name') }} - Vendor Portal</h1>
        
        @if($context['type'] === 'store_access')
            <h2>Quick Store Access</h2>
            <p>Access your {{ ucfirst($context['section']) }} section directly:</p>
            
            <a href="{{ $magicUrl }}" class="btn">Access {{ ucfirst($context['section']) }}</a>
            
        @else
            <h2>Vendor Login Link</h2>
            <p>Access your vendor dashboard:</p>
            
            <a href="{{ $magicUrl }}" class="btn">Access Vendor Portal</a>
        @endif
        
        <p><small>This secure link expires at {{ $expiresAt->format('M j, Y g:i A') }}.</small></p>
        
        <hr>
        
        <p>Direct URL:</p>
        <p style="word-break: break-all;">{{ $magicUrl }}</p>
        
        <p><small>
            For security, this link is tied to your email address and can only be used once.<br>
            If you have any concerns, please contact our vendor support team.
        </small></p>
    </div>
</body>
</html>
```

## Frontend Components

### Customer Quick Checkout

```html
<!-- resources/views/checkout/express.blade.php -->
<div class="express-checkout">
    <h3>Quick Checkout</h3>
    <p>Already have an account? Get instant access to complete your purchase.</p>
    
    <form id="expressCheckoutForm">
        @csrf
        <input type="hidden" name="cart_id" value="{{ $cart->id }}">
        
        <div class="form-group">
            <input type="email" 
                   name="email" 
                   placeholder="Enter your email" 
                   required
                   class="form-control">
        </div>
        
        <button type="submit" class="btn btn-primary">
            Send Quick Access Link
        </button>
    </form>
</div>

<script>
document.getElementById('expressCheckoutForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('/customer/auth/checkout-link', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            // Show success message or redirect
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Something went wrong. Please try again.');
    }
});
</script>
```

### Vendor Quick Access Widget

```html
<!-- resources/views/vendor/partials/quick-access.blade.php -->
<div class="quick-access-widget">
    <h4>Quick Access</h4>
    <p>Get instant access to different sections of your store:</p>
    
    <div class="access-buttons">
        <button onclick="sendQuickAccess('products')" class="btn btn-outline-primary">
            📦 Products
        </button>
        <button onclick="sendQuickAccess('orders')" class="btn btn-outline-success">
            📋 Orders
        </button>
        <button onclick="sendQuickAccess('analytics')" class="btn btn-outline-info">
            📊 Analytics
        </button>
    </div>
</div>

<script>
async function sendQuickAccess(section) {
    const email = '{{ auth()->guard('vendor')->user()->email }}';
    
    try {
        const response = await fetch('/vendor/auth/store-access', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                email: email,
                store_section: section
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
        }
    } catch (error) {
        alert('Failed to send quick access link');
    }
}
</script>
```

## Routes

```php
<?php
// routes/web.php

// Customer routes
Route::prefix('customer')->group(function () {
    Route::get('/login', [App\Http\Controllers\Customer\AuthController::class, 'showLoginForm'])
        ->name('customer.login');
    Route::post('/auth/magic-link', [App\Http\Controllers\Customer\AuthController::class, 'sendMagicLink'])
        ->name('customer.auth.magic-link');
    Route::post('/auth/checkout-link', [App\Http\Controllers\Customer\AuthController::class, 'sendCheckoutLink'])
        ->name('customer.auth.checkout-link');
});

// Vendor routes
Route::prefix('vendor')->group(function () {
    Route::get('/login', [App\Http\Controllers\Vendor\AuthController::class, 'showLoginForm'])
        ->name('vendor.login');
    Route::post('/auth/magic-link', [App\Http\Controllers\Vendor\AuthController::class, 'sendMagicLink'])
        ->name('vendor.auth.magic-link');
    Route::post('/auth/store-access', [App\Http\Controllers\Vendor\AuthController::class, 'sendStoreAccessLink'])
        ->name('vendor.auth.store-access');
});

// Admin routes
Route::prefix('admin')->group(function () {
    Route::get('/login', [App\Http\Controllers\Admin\AuthController::class, 'showLoginForm'])
        ->name('admin.login');
    Route::post('/auth/magic-link', [App\Http\Controllers\Admin\AuthController::class, 'sendMagicLink'])
        ->name('admin.auth.magic-link');
});
```

## Security Enhancements

### Rate Limiting

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        'throttle:magic-links',
    ],
];

protected $middlewareAliases = [
    'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
];

// In RouteServiceProvider
public function boot()
{
    RateLimiter::for('magic-links', function (Request $request) {
        return Limit::perMinute(3)->by($request->ip()); // 3 requests per minute
    });
}
```

### Audit Logging

```php
// app/Listeners/LogMagicLinkUsage.php
namespace App\Listeners;

use Grazulex\OneClickLogin\Events\MagicLinkUsed;
use Illuminate\Support\Facades\Log;

class LogMagicLinkUsage
{
    public function handle(MagicLinkUsed $event)
    {
        Log::info('Magic link used', [
            'email' => $event->email,
            'ip' => $event->ipAddress,
            'user_agent' => $event->userAgent,
            'guard' => config('oneclicklogin.guard'),
            'timestamp' => now(),
        ]);
    }
}
```

## Performance Optimization

### Queueing Magic Link Emails

```php
// app/Jobs/SendMagicLinkEmail.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendMagicLinkEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public string $email,
        public string $magicUrl,
        public string $mailClass,
        public array $context = []
    ) {}
    
    public function handle()
    {
        Mail::to($this->email)->send(
            new $this->mailClass($this->magicUrl, $this->context)
        );
    }
}
```

## Testing

### Feature Tests

```php
// tests/Feature/CustomerMagicLinkTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Services\MagicLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerMagicLinkTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_customer_can_request_magic_link()
    {
        $customer = Customer::factory()->create([
            'email' => 'customer@example.com'
        ]);
        
        $response = $this->post('/customer/auth/magic-link', [
            'email' => 'customer@example.com'
        ]);
        
        $response->assertRedirect()
                 ->assertSessionHas('success');
    }
    
    public function test_checkout_magic_link_works()
    {
        $customer = Customer::factory()->create();
        $cart = Cart::factory()->create(['customer_id' => $customer->id]);
        
        $response = $this->postJson('/customer/auth/checkout-link', [
            'email' => $customer->email,
            'cart_id' => $cart->id
        ]);
        
        $response->assertJson(['success' => true]);
    }
}
```

This complete e-commerce example shows how to integrate Laravel OneClickLogin into a real-world application with multiple user types, secure authentication flows, and production-ready features.
