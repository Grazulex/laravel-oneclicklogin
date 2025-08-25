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
        return $this->link !== null && $this->error === null;
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
     * Consume the magic link (mark as used)
     */
    public function consume(?string $ipAddress = null, ?string $userAgent = null): ?MagicLink
    {
        if (! $this->canUse()) {
            return null;
        }

        $this->link->markAsUsed($ipAddress, $userAgent);

        event(new MagicLinkUsed($this->link));

        return $this->link;
    }

    /**
     * Validate the token and find the magic link
     */
    protected function validateToken(): void
    {
        // Find magic links and check which one matches the token
        $links = MagicLink::where('expires_at', '>', now())
            ->whereNull('used_at')
            ->get();

        foreach ($links as $link) {
            if ($link->verifyToken($this->token)) {
                $this->link = $link;

                return;
            }
        }

        // If we get here, no valid link was found
        $this->error = 'Invalid or expired magic link';

        event(new MagicLinkInvalid($this->token));
    }
}
