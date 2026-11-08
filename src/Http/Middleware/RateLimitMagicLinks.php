<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Http\Middleware;

use Closure;
use Grazulex\OneClickLogin\Support\InvalidLinkRedirect;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RateLimitMagicLinks
{
    /**
     * When no attempt count is given (or 0), the limit is read from
     * `rate_limit.consume_per_ip_per_min` with a 1 minute window. A configured
     * limit of 0 disables rate limiting. Explicit parameters keep a 60 minute
     * default window.
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 0, int $decayMinutes = 0): HttpResponse
    {
        if ($maxAttempts <= 0) {
            $maxAttempts = (int) config('oneclicklogin.rate_limit.consume_per_ip_per_min', 20);
            $decayMinutes = $decayMinutes > 0 ? $decayMinutes : 1;

            if ($maxAttempts <= 0) {
                return $next($request);
            }
        } elseif ($decayMinutes <= 0) {
            $decayMinutes = 60;
        }

        $key = $this->resolveRequestSignature($request);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please try again in '.$seconds.' seconds.',
                'retry_after' => $seconds,
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Clear rate limiting on successful authentication
        if ($response->getStatusCode() === 302 && ! InvalidLinkRedirect::isFailureLocation($response->headers->get('location'))) {
            RateLimiter::clear($key);
        }

        return $response;
    }

    protected function resolveRequestSignature(Request $request): string
    {
        return sha1(
            $request->ip().'|'.
            $request->userAgent().'|'.
            'magic-link-verification'
        );
    }
}
