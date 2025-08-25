<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Http\Controllers;

use Grazulex\OneClickLogin\Services\MagicLinkManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $consumer = $this->manager->consume($token);

        // Check if the magic link is valid
        if (! $consumer->isValid()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $consumer->getError() ?? 'Invalid magic link',
                ], 400);
            }

            return redirect()->route('login')
                ->withErrors(['magic_link' => $consumer->getError() ?? 'Invalid magic link']);
        }

        // Check if it can be used
        if (! $consumer->canUse()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Magic link has already been used',
                ], 400);
            }

            return redirect()->route('login')
                ->withErrors(['magic_link' => 'This magic link has already been used']);
        }

        // Consume the magic link
        $link = $consumer->consume($request->ip(), $request->userAgent());

        if (! $link instanceof \Grazulex\OneClickLogin\Models\MagicLink) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to consume magic link',
                ], 500);
            }

            return redirect()->route('login')
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

        return redirect()->route('login')
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
}
