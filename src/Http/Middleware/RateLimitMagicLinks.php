<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RateLimitMagicLinks
{
    public function handle(Request $request, Closure $next, int $maxAttempts = 5, int $decayMinutes = 60): HttpResponse
    {
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
        if ($response->getStatusCode() === 302 && ! str_contains($response->headers->get('location', ''), '/login')) {
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
