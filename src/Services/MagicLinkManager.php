<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Services;

use Grazulex\OneClickLogin\Models\MagicLink;

class MagicLinkManager
{
    /**
     * Create a magic link builder for the given email.
     */
    public function for(string $email): MagicLinkBuilder
    {
        return new MagicLinkBuilder($email);
    }

    /**
     * Create a magic link consumer for validation.
     */
    public function consume(string $token): MagicConsumer
    {
        return new MagicConsumer($token);
    }

    /**
     * Prune expired magic links.
     */
    public function prune(int $days = 7): int
    {
        $cutoff = now()->subDays($days);

        return MagicLink::where(function ($query) use ($cutoff) {
            $query->where('expires_at', '<', $cutoff)
                ->orWhereNotNull('used_at');
        })->delete();
    }

    /**
     * Extend a magic link expiration.
     */
    public function extend(MagicLink $link, int $hours): MagicLink
    {
        $link->expires_at = $link->expires_at->addHours($hours);
        $link->save();

        return $link;
    }

    /**
     * Revoke a magic link.
     */
    public function revoke(MagicLink $link): MagicLink
    {
        $link->revoke();

        return $link;
    }
}
