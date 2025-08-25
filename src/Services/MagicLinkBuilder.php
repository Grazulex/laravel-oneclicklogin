<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Services;

use Carbon\Carbon;
use Exception;
use Grazulex\OneClickLogin\Events\MagicLinkCreated;
use Grazulex\OneClickLogin\Models\MagicLink;
use Illuminate\Support\Facades\RateLimiter;

class MagicLinkBuilder
{
    protected string $email;

    protected ?string $redirectUrl = null;

    protected ?Carbon $expiresAt = null;

    protected bool $skipRateLimit = false;

    protected array $context = [];

    protected array $meta = [];

    public function __construct(string $email)
    {
        $this->email = $email;

        // Set default TTL
        $defaultTtl = (int) config('oneclicklogin.ttl_minutes', 60);
        $this->expiresAt = now()->addMinutes($defaultTtl);
    }

    /**
     * Set the redirect URL after successful login
     */
    public function redirectTo(string $url): self
    {
        $this->redirectUrl = $url;

        return $this;
    }

    /**
     * Set expiration in minutes from now
     */
    public function expiresInMinutes(int $minutes): self
    {
        $this->expiresAt = now()->addMinutes($minutes);

        return $this;
    }

    /**
     * Set expiration in hours from now
     */
    public function expiresInHours(int $hours): self
    {
        $this->expiresAt = now()->addHours($hours);

        return $this;
    }

    /**
     * Set specific expiration date
     */
    public function expiresAt(Carbon $date): self
    {
        $this->expiresAt = $date;

        return $this;
    }

    /**
     * Skip rate limiting for this magic link
     */
    public function skipRateLimit(): self
    {
        $this->skipRateLimit = true;

        return $this;
    }

    /**
     * Add context data for the magic link
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Add metadata for the magic link
     */
    public function withMeta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Generate the magic link
     */
    public function generate(): MagicLink
    {
        // Apply rate limiting if not skipped
        if (! $this->skipRateLimit && $this->isRateLimited()) {
            throw new Exception('Rate limit exceeded for email: '.$this->email);
        }

        $link = $this->createMagicLink();

        event(new MagicLinkCreated($link));

        return $link;
    }

    /**
     * Create the magic link model
     */
    protected function createMagicLink(): MagicLink
    {
        $link = new MagicLink([
            'email' => $this->email,
            'redirect_url' => $this->redirectUrl ?? config('oneclicklogin.default_redirect_url', '/'),
            'expires_at' => $this->expiresAt,
            'context' => $this->context,
            'meta' => $this->meta,
        ]);

        // Generate token and hash before saving
        $link->generateToken();
        $link->save();

        return $link;
    }

    /**
     * Check if the email is rate limited
     */
    protected function isRateLimited(): bool
    {
        if (! config('oneclicklogin.rate_limiting.enabled', true)) {
            return false;
        }

        $key = 'oneclicklogin:'.$this->email;
        $maxAttempts = config('oneclicklogin.rate_limiting.max_attempts', 5);
        $decayMinutes = config('oneclicklogin.rate_limiting.decay_minutes', 60);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return true;
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        return false;
    }
}
