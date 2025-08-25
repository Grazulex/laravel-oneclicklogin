# SPA with JSON API Example

This example demonstrates how to integrate Laravel OneClickLogin with a Single Page Application (SPA) using Vue.js frontend and Laravel API backend.

## Project Architecture

- **Frontend**: Vue.js 3 with Composition API
- **Backend**: Laravel API with Sanctum authentication
- **Authentication**: Magic links via JSON API
- **State Management**: Pinia for authentication state

## Backend Setup

### API Routes

```php
<?php
// routes/api.php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MagicLinkController;

Route::prefix('auth')->group(function () {
    // Magic link authentication
    Route::post('/magic-link/request', [MagicLinkController::class, 'request']);
    Route::post('/magic-link/verify', [MagicLinkController::class, 'verify']);
    Route::get('/magic-link/status/{token}', [MagicLinkController::class, 'status']);
    
    // User authentication
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::apiResource('projects', ProjectController::class);
});
```

### Magic Link API Controller

```php
<?php
// app/Http/Controllers/Api/MagicLinkController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Grazulex\OneClickLogin\Facades\OneClickLogin;
use App\Jobs\SendMagicLinkEmail;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;

class MagicLinkController extends Controller
{
    public function request(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'redirect_url' => 'nullable|url',
            'remember' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input data',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->input('email');

        // Rate limiting
        $key = 'magic-link-request:' . $request->ip() . ':' . $email;
        
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $seconds
            ], 429);
        }

        RateLimiter::hit($key, 300); // 5 minutes

        try {
            // Check if user exists
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                // For security, don't reveal if email exists
                return response()->json([
                    'success' => true,
                    'message' => 'If an account exists with this email, a magic link has been sent.'
                ]);
            }

            $redirectUrl = $request->input('redirect_url', '/dashboard');
            $remember = $request->boolean('remember');

            // Generate magic link
            $link = OneClickLogin::for($email)
                ->redirectTo($redirectUrl)
                ->context([
                    'type' => 'spa_login',
                    'remember' => $remember,
                    'user_agent' => $request->userAgent(),
                    'ip' => $request->ip(),
                ])
                ->expiresInMinutes(15)
                ->generate();

            // Queue email sending
            SendMagicLinkEmail::dispatch(
                $email,
                $link->getUrl(),
                'api',
                [
                    'remember' => $remember,
                    'app_name' => config('app.name'),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Magic link sent to your email address.',
                'expires_in' => 15 * 60, // seconds
                'link_id' => $link->id
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send magic link. Please try again.'
            ], 500);
        }
    }

    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|size:64',
            'remember' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token format',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $consumer = OneClickLogin::consume($request->input('token'));

            if (!$consumer->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => $consumer->getError() ?? 'Invalid or expired magic link',
                    'error_code' => 'INVALID_LINK'
                ], 400);
            }

            if (!$consumer->canUse()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This magic link has already been used',
                    'error_code' => 'LINK_USED'
                ], 400);
            }

            // Consume the magic link
            $link = $consumer->consume($request->ip(), $request->userAgent());

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to consume magic link',
                    'error_code' => 'CONSUMPTION_FAILED'
                ], 500);
            }

            // Find and authenticate user
            $user = User::where('email', $link->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User account not found',
                    'error_code' => 'USER_NOT_FOUND'
                ], 404);
            }

            // Create Sanctum token
            $remember = $request->boolean('remember') || ($link->context['remember'] ?? false);
            $tokenName = 'magic-link-' . now()->timestamp;
            
            $abilities = ['*']; // or specific abilities based on user role
            $token = $user->createToken($tokenName, $abilities);

            // Set token expiration based on remember preference
            if (!$remember) {
                // Short-lived session token (8 hours)
                $token->accessToken->expires_at = now()->addHours(8);
                $token->accessToken->save();
            }
            // If remember is true, use default long expiration

            return response()->json([
                'success' => true,
                'message' => 'Successfully authenticated',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->avatar_url,
                        'roles' => $user->roles->pluck('name'),
                    ],
                    'token' => $token->plainTextToken,
                    'expires_in' => $remember ? null : 8 * 60 * 60, // seconds
                    'redirect_url' => $link->redirect_url,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error_code' => 'AUTH_FAILED'
            ], 500);
        }
    }

    public function status(Request $request, string $token): JsonResponse
    {
        try {
            $consumer = OneClickLogin::consume($token);

            return response()->json([
                'valid' => $consumer->isValid(),
                'can_use' => $consumer->canUse(),
                'error' => $consumer->getError(),
                'expires_at' => $consumer->isValid() ? $consumer->getLink()->expires_at : null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'can_use' => false,
                'error' => 'Invalid token',
                'expires_at' => null,
            ]);
        }
    }
}
```

### Authentication Controller

```php
<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'last_login' => $user->last_login_at,
            ]
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }
}
```

## Frontend Implementation

### Auth Store (Pinia)

```javascript
// stores/auth.js
import { defineStore } from 'pinia'
import axios from 'axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null,
    isAuthenticated: false,
    loading: false,
    error: null,
  }),

  getters: {
    isLoggedIn: (state) => state.isAuthenticated && state.user !== null,
    userRoles: (state) => state.user?.roles || [],
    hasRole: (state) => (role) => state.user?.roles.includes(role) || false,
  },

  actions: {
    async requestMagicLink(email, redirectUrl = '/dashboard', remember = false) {
      this.loading = true
      this.error = null

      try {
        const response = await axios.post('/api/auth/magic-link/request', {
          email,
          redirect_url: redirectUrl,
          remember
        })

        this.loading = false
        return response.data

      } catch (error) {
        this.loading = false
        this.error = error.response?.data?.message || 'Failed to send magic link'
        throw error
      }
    },

    async verifyMagicLink(token, remember = false) {
      this.loading = true
      this.error = null

      try {
        const response = await axios.post('/api/auth/magic-link/verify', {
          token,
          remember
        })

        if (response.data.success) {
          this.setAuth(response.data.data)
        }

        this.loading = false
        return response.data

      } catch (error) {
        this.loading = false
        this.error = error.response?.data?.message || 'Authentication failed'
        throw error
      }
    },

    async checkMagicLinkStatus(token) {
      try {
        const response = await axios.get(`/api/auth/magic-link/status/${token}`)
        return response.data
      } catch (error) {
        return {
          valid: false,
          can_use: false,
          error: 'Failed to check status'
        }
      }
    },

    setAuth(authData) {
      this.user = authData.user
      this.token = authData.token
      this.isAuthenticated = true

      // Store token in localStorage for persistence
      if (authData.token) {
        localStorage.setItem('auth_token', authData.token)
        // Set default authorization header
        axios.defaults.headers.common['Authorization'] = `Bearer ${authData.token}`
      }
    },

    async fetchUser() {
      if (!this.token) {
        this.loadTokenFromStorage()
      }

      if (!this.token) {
        return false
      }

      try {
        const response = await axios.get('/api/auth/user')
        if (response.data.success) {
          this.user = response.data.data
          this.isAuthenticated = true
          return true
        }
      } catch (error) {
        this.logout()
        return false
      }
    },

    loadTokenFromStorage() {
      const token = localStorage.getItem('auth_token')
      if (token) {
        this.token = token
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
      }
    },

    async logout() {
      if (this.token) {
        try {
          await axios.post('/api/auth/logout')
        } catch (error) {
          console.warn('Logout request failed:', error)
        }
      }

      this.user = null
      this.token = null
      this.isAuthenticated = false
      
      localStorage.removeItem('auth_token')
      delete axios.defaults.headers.common['Authorization']
    },

    clearError() {
      this.error = null
    }
  }
})
```

### Login Component

```vue
<!-- components/Auth/MagicLinkLogin.vue -->
<template>
  <div class="magic-link-login">
    <div class="card">
      <div class="card-header">
        <h2>Sign In</h2>
        <p>We'll send you a secure link to sign in instantly</p>
      </div>

      <div class="card-body">
        <form @submit.prevent="handleSubmit" v-if="!linkSent">
          <div class="form-group">
            <label for="email">Email Address</label>
            <input
              type="email"
              id="email"
              v-model="email"
              required
              :disabled="loading"
              class="form-control"
              placeholder="Enter your email"
            />
          </div>

          <div class="form-group">
            <label class="checkbox-wrapper">
              <input
                type="checkbox"
                v-model="remember"
                :disabled="loading"
              />
              <span>Keep me signed in</span>
            </label>
          </div>

          <button
            type="submit"
            :disabled="loading || !email"
            class="btn btn-primary btn-block"
          >
            <span v-if="loading" class="spinner"></span>
            {{ loading ? 'Sending...' : 'Send Magic Link' }}
          </button>

          <div v-if="error" class="alert alert-error">
            {{ error }}
          </div>
        </form>

        <!-- Success state -->
        <div v-else class="success-state">
          <div class="success-icon">✉️</div>
          <h3>Check Your Email</h3>
          <p>
            We've sent a magic link to <strong>{{ email }}</strong>. 
            Click the link in your email to sign in instantly.
          </p>
          
          <div class="timer" v-if="timeRemaining > 0">
            Link expires in: {{ formatTime(timeRemaining) }}
          </div>

          <button @click="resetForm" class="btn btn-secondary">
            Use Different Email
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter, useRoute } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const remember = ref(false)
const linkSent = ref(false)
const timeRemaining = ref(0)
let timer = null

const loading = computed(() => authStore.loading)
const error = computed(() => authStore.error)

async function handleSubmit() {
  try {
    authStore.clearError()
    
    const redirectUrl = route.query.redirect || '/dashboard'
    
    const response = await authStore.requestMagicLink(
      email.value,
      redirectUrl,
      remember.value
    )

    if (response.success) {
      linkSent.value = true
      timeRemaining.value = response.expires_in
      startTimer()
    }
  } catch (error) {
    console.error('Failed to send magic link:', error)
  }
}

function resetForm() {
  linkSent.value = false
  email.value = ''
  remember.value = false
  timeRemaining.value = 0
  stopTimer()
  authStore.clearError()
}

function startTimer() {
  timer = setInterval(() => {
    timeRemaining.value--
    if (timeRemaining.value <= 0) {
      stopTimer()
    }
  }, 1000)
}

function stopTimer() {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

function formatTime(seconds) {
  const minutes = Math.floor(seconds / 60)
  const remainingSeconds = seconds % 60
  return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`
}

onUnmounted(() => {
  stopTimer()
})
</script>

<style scoped>
.magic-link-login {
  max-width: 400px;
  margin: 0 auto;
  padding: 20px;
}

.card {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  overflow: hidden;
}

.card-header {
  background: #f8f9fa;
  padding: 20px;
  text-align: center;
}

.card-header h2 {
  margin: 0 0 10px 0;
  color: #333;
}

.card-header p {
  margin: 0;
  color: #666;
}

.card-body {
  padding: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
}

.form-control {
  width: 100%;
  padding: 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 16px;
}

.checkbox-wrapper {
  display: flex;
  align-items: center;
  cursor: pointer;
}

.checkbox-wrapper input {
  margin-right: 8px;
}

.btn {
  padding: 12px 24px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.2s;
}

.btn-primary {
  background: #007bff;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #0056b3;
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-block {
  width: 100%;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spinner {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid transparent;
  border-top: 2px solid currentColor;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-right: 8px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.alert {
  padding: 12px;
  border-radius: 4px;
  margin-top: 15px;
}

.alert-error {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.success-state {
  text-align: center;
}

.success-icon {
  font-size: 48px;
  margin-bottom: 20px;
}

.success-state h3 {
  margin: 0 0 15px 0;
  color: #28a745;
}

.success-state p {
  margin-bottom: 20px;
  color: #666;
}

.timer {
  background: #fff3cd;
  color: #856404;
  padding: 10px;
  border-radius: 4px;
  margin-bottom: 20px;
  font-weight: 500;
}
</style>
```

### Magic Link Verification Component

```vue
<!-- components/Auth/MagicLinkVerify.vue -->
<template>
  <div class="magic-link-verify">
    <div class="card">
      <div class="card-body">
        <div v-if="verifying" class="verifying-state">
          <div class="spinner-large"></div>
          <h3>Verifying Magic Link...</h3>
          <p>Please wait while we authenticate you.</p>
        </div>

        <div v-else-if="verified" class="success-state">
          <div class="success-icon">✅</div>
          <h3>Authentication Successful!</h3>
          <p>You're now logged in. Redirecting you...</p>
        </div>

        <div v-else class="error-state">
          <div class="error-icon">❌</div>
          <h3>Authentication Failed</h3>
          <p>{{ errorMessage }}</p>
          
          <div class="error-actions">
            <button @click="requestNewLink" class="btn btn-primary">
              Request New Link
            </button>
            <router-link to="/login" class="btn btn-secondary">
              Back to Login
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const verifying = ref(true)
const verified = ref(false)
const errorMessage = ref('')

onMounted(async () => {
  const token = route.params.token
  
  if (!token) {
    showError('Invalid magic link')
    return
  }

  try {
    // First check if the link is still valid
    const status = await authStore.checkMagicLinkStatus(token)
    
    if (!status.valid) {
      showError(status.error || 'Magic link has expired')
      return
    }

    if (!status.can_use) {
      showError('This magic link has already been used')
      return
    }

    // Verify the magic link
    const remember = route.query.remember === 'true'
    const response = await authStore.verifyMagicLink(token, remember)

    if (response.success) {
      verified.value = true
      
      // Redirect after a short delay
      setTimeout(() => {
        const redirectUrl = response.data.redirect_url || '/dashboard'
        router.push(redirectUrl)
      }, 1500)
    } else {
      showError(response.message)
    }

  } catch (error) {
    showError(error.response?.data?.message || 'Authentication failed')
  }
})

function showError(message) {
  verifying.value = false
  verified.value = false
  errorMessage.value = message
}

function requestNewLink() {
  router.push('/login')
}
</script>

<style scoped>
.magic-link-verify {
  max-width: 400px;
  margin: 50px auto;
  padding: 20px;
}

.card {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  text-align: center;
}

.card-body {
  padding: 40px 20px;
}

.verifying-state,
.success-state,
.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.spinner-large {
  width: 48px;
  height: 48px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid #007bff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 20px;
}

.success-icon,
.error-icon {
  font-size: 48px;
  margin-bottom: 20px;
}

h3 {
  margin: 0 0 10px 0;
}

.success-state h3 {
  color: #28a745;
}

.error-state h3 {
  color: #dc3545;
}

p {
  margin: 0 0 20px 0;
  color: #666;
}

.error-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: center;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  text-decoration: none;
  cursor: pointer;
  font-size: 14px;
}

.btn-primary {
  background: #007bff;
  color: white;
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
```

### Router Configuration

```javascript
// router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/components/Auth/MagicLinkLogin.vue'),
    meta: { guest: true }
  },
  {
    path: '/auth/verify/:token',
    name: 'VerifyMagicLink',
    component: () => import('@/components/Auth/MagicLinkVerify.vue'),
    meta: { guest: true }
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/Dashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/',
    redirect: '/dashboard'
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  
  // Load token from storage if not already loaded
  if (!authStore.isAuthenticated) {
    authStore.loadTokenFromStorage()
    
    if (authStore.token) {
      await authStore.fetchUser()
    }
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'Login', query: { redirect: to.fullPath } })
  } else if (to.meta.guest && authStore.isAuthenticated) {
    next({ name: 'Dashboard' })
  } else {
    next()
  }
})

export default router
```

### Main Application Setup

```javascript
// main.js
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import axios from 'axios'
import router from './router'
import App from './App.vue'

// Axios configuration
axios.defaults.baseURL = process.env.VUE_APP_API_URL || 'http://localhost:8000'
axios.defaults.headers.common['Accept'] = 'application/json'
axios.defaults.headers.common['Content-Type'] = 'application/json'
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

// Add CSRF token if available
const token = document.head.querySelector('meta[name="csrf-token"]')
if (token) {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content
}

// Response interceptor for handling auth errors
axios.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      // Token expired, redirect to login
      const authStore = useAuthStore()
      authStore.logout()
      router.push('/login')
    }
    return Promise.reject(error)
  }
)

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

app.mount('#app')
```

## Email Template for SPA

```html
<!-- resources/views/emails/spa/magic-link.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>{{ $appName }} - Sign In Link</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .btn { 
            display: inline-block; 
            padding: 15px 30px; 
            background: #007bff; 
            color: white; 
            text-decoration: none; 
            border-radius: 6px; 
            font-weight: bold;
        }
        .code-block {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            word-break: break-all;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $appName }}</h1>
            <h2>Your Sign-In Link</h2>
        </div>

        <p>Click the button below to sign in to your account:</p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $magicUrl }}" class="btn">Sign In to {{ $appName }}</a>
        </div>

        @if($remember)
        <p><strong>Note:</strong> You chose to stay signed in, so this session will last longer.</p>
        @endif

        <p>Or copy and paste this link in your browser:</p>
        <div class="code-block">{{ $magicUrl }}</div>

        <hr>

        <p><small>
            This link expires in 15 minutes and can only be used once.<br>
            If you didn't request this link, please ignore this email.
        </small></p>
    </div>
</body>
</html>
```

## Security Considerations

### Rate Limiting Configuration

```php
// config/sanctum.php
'expiration' => null, // Default to no expiration for remember tokens
'middleware' => [
    'encrypt_cookies',
    'cookie_session',
    'throttle:api', // Add rate limiting
],

// config/cors.php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['*'],
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
'allowed_origins_patterns' => [],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,
```

This SPA example demonstrates a complete implementation with Vue.js frontend, secure token-based authentication, proper error handling, and production-ready security measures.
