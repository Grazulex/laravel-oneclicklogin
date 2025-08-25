<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Http\Middleware;

use Closure;
use Grazulex\OneClickLogin\Events\MagicLinkAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogMagicLinkAttempts
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');
        $startTime = microtime(true);

        $response = $next($request);

        $this->logAttempt($request, $response, $token, $startTime);

        return $response;
    }

    protected function logAttempt(Request $request, Response $response, ?string $token, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $success = $this->isSuccessfulResponse($response);

        $logData = [
            'token' => $token !== null && $token !== '' && $token !== '0' ? mb_substr($token, 0, 8).'...' : null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'success' => $success,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'timestamp' => now()->toISOString(),
        ];

        if ($success) {
            Log::info('Magic link verification successful', $logData);
        } else {
            Log::warning('Magic link verification failed', $logData);
        }

        // Fire event for additional processing
        event(new MagicLinkAttempt(
            token: $token,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            success: $success,
            timestamp: now()
        ));
    }

    protected function isSuccessfulResponse(Response $response): bool
    {
        $statusCode = $response->getStatusCode();

        // Check for successful redirect (not to login page)
        if ($statusCode === 302) {
            $location = $response->headers->get('location', '');

            return ! str_contains($location, '/login');
        }

        // Check for successful JSON response
        if ($statusCode === 200) {
            $content = $response->getContent();
            if ($content) {
                $data = json_decode($content, true);

                return isset($data['success']) && $data['success'] === true;
            }
        }

        return false;
    }
}
