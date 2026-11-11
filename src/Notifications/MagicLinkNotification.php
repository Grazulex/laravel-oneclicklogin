<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Notifications;

use Carbon\CarbonInterface;
use Grazulex\OneClickLogin\Models\MagicLink;
use Illuminate\Notifications\Notification;

/**
 * Base class of the notifications delivering a magic link.
 *
 * The URL is captured when the notification is created: the plain token is
 * never stored, so it cannot be rebuilt from a reloaded model (e.g. when a
 * subclass is queued).
 */
abstract class MagicLinkNotification extends Notification
{
    public string $url;

    public CarbonInterface $expiresAt;

    public function __construct(public MagicLink $link)
    {
        $this->url = $link->url;
        $this->expiresAt = $link->expires_at;
    }

    /**
     * Minutes left before the link expires (at least 1).
     */
    public function minutesRemaining(): int
    {
        return (int) max(1, ceil(now()->diffInMinutes($this->expiresAt)));
    }

    /**
     * Name of the application, as shown to the recipient.
     */
    protected function appName(): string
    {
        $name = config('app.name');

        return is_string($name) && $name !== '' ? $name : 'Laravel';
    }
}
