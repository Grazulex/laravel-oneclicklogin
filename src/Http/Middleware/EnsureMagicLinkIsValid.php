<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Http\Middleware;

use Closure;
use Grazulex\OneClickLogin\Services\MagicConsumer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EnsureMagicLinkIsValid
{
    public function __construct(
        private readonly MagicConsumer $magicConsumer
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->query('token');

        if (! $token) {
            return redirect(config('oneclicklogin.redirect_on_invalid'))
                ->with('error', 'No magic link token provided');
        }

        $result = $this->magicConsumer->consume($token, $request);

        if (! $result->valid) {
            return redirect(config('oneclicklogin.redirect_on_invalid'))
                ->with('error', $result->error ?? 'Invalid magic link');
        }

        // Add the result to the request for the controller
        $request->merge(['magic_link_result' => $result]);

        return $next($request);
    }
}
