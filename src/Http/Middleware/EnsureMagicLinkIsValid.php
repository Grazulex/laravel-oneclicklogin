<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Http\Middleware;

use Closure;
use Grazulex\OneClickLogin\Services\MagicLinkManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMagicLinkIsValid
{
    public function __construct(
        private readonly MagicLinkManager $manager
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->query('token');

        if (! $token) {
            return redirect(config('oneclicklogin.login_redirect_url', '/login'))
                ->with('error', 'No magic link token provided');
        }

        $consumer = $this->manager->consume($token);

        if (! $consumer->isValid()) {
            return redirect(config('oneclicklogin.login_redirect_url', '/login'))
                ->with('error', 'Invalid magic link');
        }

        // Add the consumer to the request for the controller
        $request->merge(['magic_link_consumer' => $consumer]);

        return $next($request);
    }
}
