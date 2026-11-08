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

    protected ?string $boundIp = null;

    protected ?string $boundUserAgent = null;

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
     * Bind the magic link to an IP address (defaults to the current request IP).
     * The link can then only be consumed from that IP address.
     */
    public function bindToIp(?string $ipAddress = null): self
    {
        $this->boundIp = $ipAddress ?? request()->ip();

        return $this;
    }

    /**
     * Bind the magic link to a device user agent (defaults to the current request user agent).
     * The link can then only be consumed from that device.
     */
    public function bindToDevice(?string $userAgent = null): self
    {
        $this->boundUserAgent = $userAgent ?? request()->userAgent();

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
        $attributes = [
            'email' => $this->email,
            'redirect_url' => $this->redirectUrl ?? config('oneclicklogin.default_redirect_url', '/'),
            'expires_at' => $this->expiresAt,
            'context' => $this->context,
            'meta' => $this->meta,
        ];

        // IP / device binding: stored in ip_address / user_agent until the link is used
        $boundIp = $this->boundIp ?? ($this->autoBindingEnabled('ip_binding') ? request()->ip() : null);
        $boundUserAgent = $this->boundUserAgent ?? ($this->autoBindingEnabled('device_binding') ? request()->userAgent() : null);

        if (! in_array($boundIp, [null, ''], true)) {
            $attributes['ip_address'] = $boundIp;
        }

        if (! in_array($boundUserAgent, [null, ''], true)) {
            $attributes['user_agent'] = $boundUserAgent;
        }

        $link = new MagicLink($attributes);

        // Generate token and hash before saving
        $link->generateToken();
        $link->save();

        return $link;
    }

    /**
     * Whether a binding option is enabled and a client request is available
     * (links generated from the console or queued jobs are never bound automatically).
     * The option is read at the top level, or under `security` as an alias.
     */
    protected function autoBindingEnabled(string $option): bool
    {
        $enabled = filter_var(config('oneclicklogin.'.$option, false), FILTER_VALIDATE_BOOLEAN)
            || filter_var(config('oneclicklogin.security.'.$option, false), FILTER_VALIDATE_BOOLEAN);

        return $enabled && ! app()->runningInConsole();
    }

    /**
     * Check if the email is rate limited
     *
     * Reads `rate_limit.issue_per_email_per_hour`. The legacy `rate_limiting.*` keys
     * (`enabled`, `max_attempts`, `decay_minutes`) are still honored when set.
     */
    protected function isRateLimited(): bool
    {
        $legacy = config('oneclicklogin.rate_limiting');
        $legacy = is_array($legacy) ? $legacy : [];

        if (array_key_exists('enabled', $legacy) && ! filter_var($legacy['enabled'], FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $maxAttempts = (int) ($legacy['max_attempts'] ?? config('oneclicklogin.rate_limit.issue_per_email_per_hour', 5));
        $decayMinutes = (int) ($legacy['decay_minutes'] ?? 60);

        // A limit of 0 (or less) disables rate limiting
        if ($maxAttempts <= 0) {
            return false;
        }

        $decayMinutes = max(1, $decayMinutes);
        $key = 'oneclicklogin:'.$this->email;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return true;
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        return false;
    }
}
