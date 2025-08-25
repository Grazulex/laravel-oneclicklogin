# Multi-Tenant Application Example

This example demonstrates how to implement Laravel OneClickLogin in a multi-tenant SaaS application where users can belong to multiple organizations with different roles.

## Application Architecture

- **Multi-Tenancy**: Organization-based tenancy with shared database
- **User Management**: Users can belong to multiple organizations
- **Role System**: Different roles per organization (Owner, Admin, Member, Guest)
- **Authentication**: Magic links with tenant context
- **Subdomain Routing**: `{tenant}.app.com` format

## Database Schema

### Migrations

```php
<?php
// database/migrations/create_organizations_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('organizations');
    }
};
```

```php
<?php
// database/migrations/create_organization_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('organization_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('role')->default('member'); // owner, admin, member, guest
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('organization_users');
    }
};
```

## Models

### Organization Model

```php
<?php
// app/Models/Organization.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'settings',
        'is_active',
        'trial_ends_at',
        'suspended_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot(['role', 'permissions', 'is_active', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    public function activeUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('is_active', true);
    }

    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'owner');
    }

    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'admin');
    }

    public function isActive(): bool
    {
        return $this->is_active && !$this->suspended_at;
    }

    public function isTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at > now();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getUrl(string $path = ''): string
    {
        $domain = $this->domain ?: $this->slug . '.' . config('app.domain');
        $protocol = app()->environment('production') ? 'https' : 'http';
        
        return $protocol . '://' . $domain . '/' . ltrim($path, '/');
    }
}
```

### Enhanced User Model

```php
<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'avatar',
        'timezone',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_users')
            ->withPivot(['role', 'permissions', 'is_active', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    public function activeOrganizations(): BelongsToMany
    {
        return $this->organizations()
            ->wherePivot('is_active', true)
            ->where('organizations.is_active', true);
    }

    public function hasRoleInOrganization(Organization $organization, string|array $roles): bool
    {
        $membership = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->first();

        if (!$membership || !$membership->pivot->is_active) {
            return false;
        }

        $roles = is_array($roles) ? $roles : [$roles];
        return in_array($membership->pivot->role, $roles);
    }

    public function getRoleInOrganization(Organization $organization): ?string
    {
        $membership = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->first();

        return $membership?->pivot->role;
    }

    public function getPermissionsInOrganization(Organization $organization): array
    {
        $membership = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->first();

        return $membership?->pivot->permissions ?? [];
    }

    public function canAccessOrganization(Organization $organization): bool
    {
        return $this->organizations()
            ->where('organizations.id', $organization->id)
            ->wherePivot('is_active', true)
            ->exists() && $organization->isActive();
    }
}
```

## Multi-Tenant Magic Link System

### Tenant-Aware Magic Link Controller

```php
<?php
// app/Http/Controllers/TenantMagicLinkController.php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Jobs\SendTenantMagicLinkEmail;
use Grazulex\OneClickLogin\Facades\OneClickLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;

class TenantMagicLinkController extends Controller
{
    public function requestLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'organization_slug' => 'required|string|exists:organizations,slug',
            'remember' => 'boolean',
            'redirect_to' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $organizationSlug = $request->input('organization_slug');
        $email = $request->input('email');

        // Rate limiting per organization and email
        $key = "magic-link-request:{$organizationSlug}:{$email}:" . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'email' => "Too many requests. Please try again in {$seconds} seconds."
            ])->withInput();
        }

        RateLimiter::hit($key, 300); // 5 minutes

        try {
            $organization = Organization::where('slug', $organizationSlug)->firstOrFail();

            if (!$organization->isActive()) {
                return back()->withErrors([
                    'organization' => 'This organization is currently inactive.'
                ])->withInput();
            }

            // Check if user exists and has access to this organization
            $user = User::where('email', $email)->first();
            
            if (!$user || !$user->canAccessOrganization($organization)) {
                // For security, don't reveal whether user exists or has access
                return back()->with('status', 
                    'If you have access to this organization, a magic link has been sent to your email.'
                );
            }

            $redirectTo = $request->input('redirect_to', '/dashboard');
            $remember = $request->boolean('remember', false);

            // Generate tenant-aware magic link
            $link = OneClickLogin::for($email)
                ->redirectTo($redirectTo)
                ->context([
                    'organization_id' => $organization->id,
                    'organization_slug' => $organization->slug,
                    'tenant_domain' => $organization->domain,
                    'user_id' => $user->id,
                    'remember' => $remember,
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                ])
                ->expiresInMinutes(15)
                ->generate();

            // Queue tenant-specific email
            SendTenantMagicLinkEmail::dispatch(
                $user,
                $organization,
                $link,
                $remember
            );

            return back()->with('status', 
                'A magic link has been sent to your email address.'
            );

        } catch (\Exception $e) {
            return back()->withErrors([
                'email' => 'Failed to send magic link. Please try again.'
            ])->withInput();
        }
    }

    public function consumeLink(Request $request, string $token)
    {
        try {
            $consumer = OneClickLogin::consume($token);

            if (!$consumer->isValid()) {
                return redirect()->route('tenant.login', ['organization' => $request->route('organization')])
                    ->withErrors(['token' => 'Invalid or expired magic link.']);
            }

            if (!$consumer->canUse()) {
                return redirect()->route('tenant.login', ['organization' => $request->route('organization')])
                    ->withErrors(['token' => 'This magic link has already been used.']);
            }

            // Consume the link
            $link = $consumer->consume($request->ip(), $request->userAgent());

            if (!$link) {
                return redirect()->route('tenant.login', ['organization' => $request->route('organization')])
                    ->withErrors(['token' => 'Failed to process magic link.']);
            }

            // Verify tenant context
            $context = $link->context;
            $organization = Organization::find($context['organization_id']);

            if (!$organization || !$organization->isActive()) {
                return redirect()->route('tenant.login', ['organization' => $request->route('organization')])
                    ->withErrors(['organization' => 'Organization not found or inactive.']);
            }

            // Find and authenticate user
            $user = User::find($context['user_id']);

            if (!$user || !$user->canAccessOrganization($organization)) {
                return redirect()->route('tenant.login', ['organization' => $organization->slug])
                    ->withErrors(['access' => 'You do not have access to this organization.']);
            }

            // Set current organization in session
            session(['current_organization' => $organization->id]);

            // Authenticate user
            Auth::login($user, $context['remember'] ?? false);

            // Update last login timestamp
            $user->update(['last_login_at' => now()]);

            // Log the successful login
            activity()
                ->performedOn($organization)
                ->causedBy($user)
                ->withProperties([
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'auth_method' => 'magic_link',
                ])
                ->log('User logged in via magic link');

            $redirectTo = $link->redirect_url ?: route('tenant.dashboard', ['organization' => $organization->slug]);
            
            return redirect()->to($redirectTo);

        } catch (\Exception $e) {
            return redirect()->route('tenant.login', ['organization' => $request->route('organization')])
                ->withErrors(['token' => 'Authentication failed. Please try again.']);
        }
    }
}
```

### Multi-Tenant Middleware

```php
<?php
// app/Http/Middleware/TenantMiddleware.php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $organization = $this->resolveOrganization($request);

        if (!$organization) {
            return $this->handleInvalidTenant($request);
        }

        if (!$organization->isActive()) {
            return $this->handleInactiveTenant($request, $organization);
        }

        // Set current organization
        app()->singleton('current_organization', fn() => $organization);
        
        // Add organization to request
        $request->merge(['organization' => $organization]);

        // Verify user access if authenticated
        if (Auth::check()) {
            $user = Auth::user();
            
            if (!$user->canAccessOrganization($organization)) {
                Auth::logout();
                return redirect()->route('tenant.login', ['organization' => $organization->slug])
                    ->withErrors(['access' => 'You do not have access to this organization.']);
            }

            // Set current organization in session
            session(['current_organization' => $organization->id]);
        }

        return $next($request);
    }

    protected function resolveOrganization(Request $request): ?Organization
    {
        // Try to resolve from route parameter first
        if ($organizationSlug = $request->route('organization')) {
            return Organization::where('slug', $organizationSlug)->first();
        }

        // Try to resolve from subdomain
        $host = $request->getHost();
        $domain = config('app.domain');

        if (str_ends_with($host, $domain)) {
            $subdomain = str_replace('.' . $domain, '', $host);
            
            // Skip www and app subdomains
            if (!in_array($subdomain, ['www', 'app', 'api'])) {
                return Organization::where('slug', $subdomain)
                    ->orWhere('domain', $host)
                    ->first();
            }
        }

        // Try to resolve from custom domain
        return Organization::where('domain', $host)->first();
    }

    protected function handleInvalidTenant(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Invalid tenant',
                'message' => 'The requested organization was not found.'
            ], 404);
        }

        return redirect()->route('home')
            ->withErrors(['organization' => 'Organization not found.']);
    }

    protected function handleInactiveTenant(Request $request, Organization $organization)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Tenant inactive',
                'message' => 'This organization is currently inactive.'
            ], 403);
        }

        return view('tenant.inactive', compact('organization'));
    }
}
```

### Routes Configuration

```php
<?php
// routes/tenant.php

use App\Http\Controllers\TenantMagicLinkController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\TeamController;
use App\Http\Controllers\Tenant\InvitationController;

// Tenant routes with organization context
Route::prefix('{organization}')->middleware(['tenant'])->group(function () {
    
    // Authentication routes
    Route::middleware('guest')->group(function () {
        Route::get('/login', function () {
            return view('tenant.auth.login');
        })->name('tenant.login');
        
        Route::post('/magic-link/request', [TenantMagicLinkController::class, 'requestLink'])
            ->name('tenant.magic-link.request');
    });

    Route::get('/magic-link/{token}', [TenantMagicLinkController::class, 'consumeLink'])
        ->name('tenant.magic-link.consume');

    // Protected tenant routes
    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('tenant.dashboard');

        // Team management
        Route::prefix('team')->group(function () {
            Route::get('/', [TeamController::class, 'index'])->name('tenant.team.index');
            Route::get('/invite', [TeamController::class, 'invite'])->name('tenant.team.invite');
            Route::post('/invite', [InvitationController::class, 'send'])->name('tenant.invitation.send');
        });

        // Organization switching
        Route::post('/switch', function (Request $request) {
            $organizationId = $request->input('organization_id');
            $organization = auth()->user()->activeOrganizations()
                ->where('organizations.id', $organizationId)
                ->first();

            if ($organization) {
                session(['current_organization' => $organization->id]);
                return redirect()->route('tenant.dashboard', ['organization' => $organization->slug]);
            }

            return back()->withErrors(['organization' => 'Invalid organization.']);
        })->name('tenant.switch');

        Route::post('/logout', function () {
            Auth::logout();
            return redirect()->route('tenant.login', ['organization' => request()->route('organization')]);
        })->name('tenant.logout');
    });
});
```

### Tenant Login View

```blade
{{-- resources/views/tenant/auth/login.blade.php --}}
@extends('tenant.layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-blue-100">
                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Sign in to {{ $organization->name }}
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                We'll send you a secure link to sign in instantly
            </p>
        </div>

        <form class="mt-8 space-y-6" method="POST" action="{{ route('tenant.magic-link.request', ['organization' => $organization->slug]) }}">
            @csrf
            <input type="hidden" name="organization_slug" value="{{ $organization->slug }}">
            
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="email" class="sr-only">Email address</label>
                    <input 
                        id="email" 
                        name="email" 
                        type="email" 
                        autocomplete="email" 
                        required 
                        class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm @error('email') border-red-300 @enderror" 
                        placeholder="Email address"
                        value="{{ old('email') }}"
                    >
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center">
                <input 
                    id="remember" 
                    name="remember" 
                    type="checkbox" 
                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    {{ old('remember') ? 'checked' : '' }}
                >
                <label for="remember" class="ml-2 block text-sm text-gray-900">
                    Keep me signed in
                </label>
            </div>

            @if (session('status'))
                <div class="bg-green-50 border border-green-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">
                                {{ session('status') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            @foreach ($errors->all() as $error)
                                <p class="text-sm font-medium text-red-800">{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <div>
                <button 
                    type="submit" 
                    class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    id="submit-btn"
                >
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-blue-500 group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </span>
                    <span id="btn-text">Send Magic Link</span>
                </button>
            </div>

            @if($organization->isTrial())
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 text-center">
                    <p class="text-sm text-yellow-800">
                        <strong>Trial Organization</strong><br>
                        Trial expires on {{ $organization->trial_ends_at->format('M j, Y') }}
                    </p>
                </div>
            @endif
        </form>
    </div>
</div>

<script>
document.querySelector('form').addEventListener('submit', function() {
    const btn = document.getElementById('submit-btn');
    const btnText = document.getElementById('btn-text');
    
    btn.disabled = true;
    btnText.textContent = 'Sending...';
});
</script>
@endsection
```

### Team Invitation System

```php
<?php
// app/Http/Controllers/Tenant/InvitationController.php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\SendTenantInvitationEmail;
use App\Models\Organization;
use App\Models\User;
use Grazulex\OneClickLogin\Facades\OneClickLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InvitationController extends Controller
{
    public function send(Request $request)
    {
        $organization = app('current_organization');
        
        // Check permissions
        if (!Auth::user()->hasRoleInOrganization($organization, ['owner', 'admin'])) {
            return back()->withErrors(['permission' => 'You do not have permission to invite users.']);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'role' => 'required|in:admin,member,guest',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $email = $request->input('email');
        $role = $request->input('role');
        $message = $request->input('message');

        // Check if user is already a member
        $existingMember = $organization->users()
            ->where('email', $email)
            ->exists();

        if ($existingMember) {
            return back()->withErrors(['email' => 'This user is already a member of the organization.']);
        }

        try {
            DB::beginTransaction();

            // Find or create user
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => explode('@', $email)[0]]
            );

            // Create organization membership with invited status
            $organization->users()->attach($user->id, [
                'role' => $role,
                'is_active' => false, // Will be activated when they accept
                'invited_at' => now(),
            ]);

            // Generate invitation magic link
            $link = OneClickLogin::for($email)
                ->redirectTo('/dashboard')
                ->context([
                    'type' => 'invitation',
                    'organization_id' => $organization->id,
                    'organization_slug' => $organization->slug,
                    'inviter_id' => Auth::id(),
                    'invited_role' => $role,
                    'custom_message' => $message,
                ])
                ->expiresInDays(7) // Invitations expire in 7 days
                ->generate();

            // Send invitation email
            SendTenantInvitationEmail::dispatch(
                $user,
                $organization,
                Auth::user(),
                $link,
                $role,
                $message
            );

            DB::commit();

            return back()->with('status', "Invitation sent to {$email} successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['email' => 'Failed to send invitation. Please try again.']);
        }
    }

    public function accept(Request $request, string $token)
    {
        try {
            $consumer = OneClickLogin::consume($token);

            if (!$consumer->isValid()) {
                return redirect()->route('home')
                    ->withErrors(['token' => 'Invalid or expired invitation link.']);
            }

            $link = $consumer->consume($request->ip(), $request->userAgent());
            $context = $link->context;

            if ($context['type'] !== 'invitation') {
                return redirect()->route('home')
                    ->withErrors(['token' => 'Invalid invitation link.']);
            }

            $organization = Organization::find($context['organization_id']);
            
            if (!$organization || !$organization->isActive()) {
                return redirect()->route('home')
                    ->withErrors(['organization' => 'Organization not found or inactive.']);
            }

            $user = User::where('email', $link->email)->first();

            if (!$user) {
                return redirect()->route('home')
                    ->withErrors(['user' => 'User account not found.']);
            }

            // Activate the membership
            $organization->users()->updateExistingPivot($user->id, [
                'is_active' => true,
                'joined_at' => now(),
            ]);

            // Set current organization and authenticate
            session(['current_organization' => $organization->id]);
            Auth::login($user);

            // Log the invitation acceptance
            activity()
                ->performedOn($organization)
                ->causedBy($user)
                ->withProperties([
                    'inviter_id' => $context['inviter_id'],
                    'role' => $context['invited_role'],
                ])
                ->log('User accepted organization invitation');

            return redirect()->route('tenant.dashboard', ['organization' => $organization->slug])
                ->with('status', "Welcome to {$organization->name}!");

        } catch (\Exception $e) {
            return redirect()->route('home')
                ->withErrors(['token' => 'Failed to process invitation. Please try again.']);
        }
    }
}
```

## Email Templates

### Tenant Magic Link Email

```php
<?php
// app/Jobs/SendTenantMagicLinkEmail.php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\User;
use Grazulex\OneClickLogin\Models\MagicLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTenantMagicLinkEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Organization $organization,
        public MagicLink $link,
        public bool $remember = false
    ) {}

    public function handle(): void
    {
        Mail::send('emails.tenant.magic-link', [
            'user' => $this->user,
            'organization' => $this->organization,
            'magicUrl' => $this->link->getUrl(),
            'remember' => $this->remember,
            'expiresIn' => $this->link->expires_at->diffForHumans(),
        ], function ($message) {
            $message->to($this->user->email, $this->user->name)
                    ->subject("Sign in to {$this->organization->name}");
        });
    }
}
```

This multi-tenant example provides a complete implementation with organization-based tenancy, role-based access control, tenant-aware magic links, and comprehensive user invitation system.
