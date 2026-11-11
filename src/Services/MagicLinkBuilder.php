<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Services;

use Carbon\Carbon;
use Exception;
use Grazulex\OneClickLogin\Events\MagicLinkCreated;
use Grazulex\OneClickLogin\Models\MagicLink;
use Grazulex\OneClickLogin\Notifications\MagicLinkMail;
use Grazulex\OneClickLogin\Notifications\MagicLinkSms;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use ReflectionClass;

class MagicLinkBuilder
{
    /**
     * Notification classes used when the configured one does not exist.
     *
     * @var array<string, class-string<Notification>>
     */
    public const DEFAULT_NOTIFICATIONS = [
        'mail' => MagicLinkMail::class,
        'sms' => MagicLinkSms::class,
    ];

    protected string $email;

    protected ?object $notifiable = null;

    /**
     * Delivery channels used by send() (keys of the `notifications` configuration).
     *
     * @var array<int, string>
     */
    protected array $channels = ['mail'];

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
     * Set the recipient notified by send() (e.g. a user using the Notifiable trait).
     * Without a recipient, send() emails the link to the builder email address.
     */
    public function to(object $notifiable): self
    {
        $this->notifiable = $notifiable;

        return $this;
    }

    /**
     * Set the delivery channels used by send(): 'mail', 'sms' or any other key
     * of the `notifications` configuration.
     *
     * @param  string|array<int, string>  $channels
     */
    public function via(string|array ...$channels): self
    {
        $flattened = [];

        array_walk_recursive($channels, function (mixed $channel) use (&$flattened): void {
            if (is_string($channel) && mb_trim($channel) !== '') {
                $flattened[] = mb_trim($channel);
            }
        });

        if ($flattened === []) {
            throw new InvalidArgumentException('At least one delivery channel is required.');
        }

        $this->channels = array_values(array_unique($flattened));

        return $this;
    }

    /**
     * Generate the magic link and deliver it through the selected channels.
     *
     * Each channel uses the notification class configured under
     * `notifications.<channel>` (the package notification when that class does
     * not exist). The configuration is checked before the link is generated.
     *
     * @throws InvalidArgumentException when a channel cannot be delivered
     */
    public function send(): MagicLink
    {
        $notifications = [];

        foreach ($this->channels as $channel) {
            $notifications[$channel] = $this->notificationClassFor($channel);
        }

        $routable = $this->notifiable !== null && method_exists($this->notifiable, 'routeNotificationFor');

        if (! $routable && array_diff($this->channels, ['mail']) !== []) {
            throw new InvalidArgumentException('Delivering a magic link through ['.implode(', ', array_diff($this->channels, ['mail'])).'] requires a notifiable recipient: call to($user) first.');
        }

        $link = $this->generate();

        foreach ($notifications as $class) {
            // Configured classes receive the link as first constructor argument.
            $notification = (new ReflectionClass($class))->newInstance($link);

            if ($routable) {
                NotificationFacade::send($this->notifiable, $notification);
            } else {
                NotificationFacade::route('mail', $this->email)->notify($notification);
            }
        }

        return $link;
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
     * Resolve the notification class delivering the link through a channel.
     *
     * @return class-string<Notification>
     */
    protected function notificationClassFor(string $channel): string
    {
        $configured = config('oneclicklogin.notifications.'.$channel);

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            if (! is_subclass_of($configured, Notification::class)) {
                throw new InvalidArgumentException("Notification class [{$configured}] for the [{$channel}] channel must extend ".Notification::class.'.');
            }

            return $configured;
        }

        if (isset(self::DEFAULT_NOTIFICATIONS[$channel])) {
            return self::DEFAULT_NOTIFICATIONS[$channel];
        }

        throw new InvalidArgumentException("No notification class configured for the [{$channel}] delivery channel (oneclicklogin.notifications.{$channel}).");
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
