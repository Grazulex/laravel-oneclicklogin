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

        return MagicLink::where(function ($query) use ($cutoff): void {
            $query->where('expires_at', '<', $cutoff)
                ->orWhereNotNull('used_at');
        })->delete();
    }

    /**
     * Extend a magic link expiration by token.
     */
    public function extend(string $token, int $hours): MagicLink
    {
        // Find the magic link by verifying the token
        $links = MagicLink::all();

        foreach ($links as $link) {
            if ($link->verifyToken($token)) {
                $link->expires_at = $link->expires_at->addHours($hours);
                $link->save();

                return $link;
            }
        }

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
    }

    /**
     * Revoke a magic link by token.
     */
    public function revoke(string $token): MagicLink
    {
        // Find the magic link by verifying the token
        $links = MagicLink::all();

        foreach ($links as $link) {
            if ($link->verifyToken($token)) {
                $link->revoke();

                return $link;
            }
        }

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
    }
}
