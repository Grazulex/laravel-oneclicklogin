<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Http\Controllers;

use Grazulex\OneClickLogin\Models\MagicLink;
use Grazulex\OneClickLogin\Services\MagicLinkManager;
use Grazulex\OneClickLogin\Support\InvalidLinkRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class MagicLinkController
{
    public function __construct(
        protected MagicLinkManager $manager
    ) {}

    /**
     * Verify and consume a magic link
     */
    public function verify(string $token, Request $request): RedirectResponse|JsonResponse
    {
        // Enforce HTTPS when required (security.require_https)
        if ($this->configFlag('security.require_https') && ! $request->secure()) {
            return $this->invalidResponse($request, 'Magic links must be opened over HTTPS', 403);
        }

        // Enforce the URL signature when signed URLs are enabled (security.signed_urls)
        if ($this->configFlag('security.signed_urls') && ! URL::hasValidSignature($request, false)) {
            return $this->invalidResponse($request, 'Invalid or expired magic link signature', 403);
        }

        $consumer = $this->manager->consume($token);

        // Check if the magic link is valid
        if (! $consumer->isValid()) {
            return $this->invalidResponse($request, $consumer->getError() ?? 'Invalid magic link', 400);
        }

        // Check if it can be used
        if (! $consumer->canUse()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Magic link has already been used',
                ], 400);
            }

            return redirect()->to(InvalidLinkRedirect::url())
                ->withErrors(['magic_link' => 'This magic link has already been used']);
        }

        // Check IP / device bindings (ip_binding, device_binding)
        if (! $consumer->allowsClient($request->ip(), $request->userAgent())) {
            return $this->invalidResponse($request, 'This magic link cannot be used from this device or network', 403);
        }

        // Consume the magic link
        $link = $consumer->consume($request->ip(), $request->userAgent());

        if (! $link instanceof MagicLink) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to consume magic link',
                ], 500);
            }

            return redirect()->to(InvalidLinkRedirect::url())
                ->withErrors(['magic_link' => 'Something went wrong']);
        }

        // Try to find and authenticate the user
        $user = $this->findUserByEmail($link->email);

        if ($user) {
            $guard = config('oneclicklogin.guard', 'web');
            Auth::guard($guard)->login($user);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully authenticated',
                    'redirect_url' => $link->redirect_url,
                ]);
            }

            return redirect()->to($link->redirect_url)
                ->with('success', 'Welcome back! You have been successfully logged in.');
        }

        // User not found - handle based on configuration
        $allowUnknownUsers = config('oneclicklogin.allow_unknown_users', false);

        if ($allowUnknownUsers) {
            // Store magic link data in session for registration process
            session([
                'magic_link_email' => $link->email,
                'magic_link_context' => $link->context,
                'magic_link_redirect' => $link->redirect_url,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'User not found, proceed to registration',
                    'redirect_url' => route('register'),
                    'email' => $link->email,
                ]);
            }

            return redirect()->route('register')
                ->with('info', 'Please complete your registration to continue.');
        }

        // Unknown users not allowed
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address',
            ], 404);
        }

        return redirect()->to(InvalidLinkRedirect::url())
            ->withErrors(['magic_link' => 'No account found with this email address']);
    }

    /**
     * Find user by email - can be overridden in configuration
     */
    protected function findUserByEmail(string $email)
    {
        $userModel = config('oneclicklogin.user_model', config('auth.providers.users.model', 'Illuminate\\Foundation\\Auth\\User'));
        $emailField = config('oneclicklogin.email_field', 'email');

        return $userModel::where($emailField, $email)->first();
    }

    /**
     * Build the response for a rejected magic link (redirect_on_invalid for HTML requests)
     */
    protected function invalidResponse(Request $request, string $message, int $status): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        return redirect()->to(InvalidLinkRedirect::url())
            ->withErrors(['magic_link' => $message]);
    }

    protected function configFlag(string $key): bool
    {
        return filter_var(config('oneclicklogin.'.$key, false), FILTER_VALIDATE_BOOLEAN);
    }
}
