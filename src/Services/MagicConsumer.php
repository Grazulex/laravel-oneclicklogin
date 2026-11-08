<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Services;

use Grazulex\OneClickLogin\Events\MagicLinkInvalid;
use Grazulex\OneClickLogin\Events\MagicLinkUsed;
use Grazulex\OneClickLogin\Models\MagicLink;

class MagicConsumer
{
    protected string $token;

    protected ?MagicLink $link = null;

    protected ?string $error = null;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->validateToken();
    }

    /**
     * Check if the magic link is valid
     */
    public function isValid(): bool
    {
        return $this->link instanceof MagicLink && $this->error === null;
    }

    /**
     * Check if the magic link can be used (valid and not used)
     */
    public function canUse(): bool
    {
        return $this->isValid() && ! $this->link->isUsed();
    }

    /**
     * Get the magic link instance
     */
    public function getMagicLink(): ?MagicLink
    {
        return $this->link;
    }

    /**
     * Get the error message if invalid
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Check if the given client (IP address / user agent) satisfies the
     * IP and device bindings of the magic link
     */
    public function allowsClient(?string $ipAddress, ?string $userAgent): bool
    {
        return $this->link instanceof MagicLink && $this->link->allowsClient($ipAddress, $userAgent);
    }

    /**
     * Consume the magic link (mark as used)
     */
    public function consume(?string $ipAddress = null, ?string $userAgent = null): ?MagicLink
    {
        if (! $this->canUse() || ! $this->allowsClient($ipAddress, $userAgent)) {
            return null;
        }

        $this->link->markAsUsed($ipAddress, $userAgent);

        event(new MagicLinkUsed($this->link, $ipAddress, $userAgent));

        return $this->link;
    }

    /**
     * Validate the token and find the magic link
     */
    protected function validateToken(): void
    {
        // Find the active magic link matching the token
        $link = MagicLink::findByToken(
            $this->token,
            MagicLink::where('expires_at', '>', now())->whereNull('used_at')
        );

        if ($link instanceof MagicLink) {
            $this->link = $link;

            return;
        }

        // If we get here, no valid link was found
        $this->error = 'Invalid or expired magic link';

        event(new MagicLinkInvalid($this->token));
    }
}
