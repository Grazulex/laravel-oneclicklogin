<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Services;

use Grazulex\OneClickLogin\Models\MagicLink;
use InvalidArgumentException;

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
     * Create a magic link builder for a notifiable recipient (e.g. a user).
     *
     * The link email is the recipient mail route (routeNotificationFor('mail')),
     * or its `email_field` attribute. send() then notifies the recipient.
     *
     * @throws InvalidArgumentException when no email address can be resolved
     */
    public function to(object $notifiable): MagicLinkBuilder
    {
        return $this->for($this->resolveEmail($notifiable))->to($notifiable);
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
        $link = MagicLink::findByToken($token);

        if ($link instanceof MagicLink) {
            $link->expires_at = $link->expires_at->addHours($hours);
            $link->save();

            return $link;
        }

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
    }

    /**
     * Revoke a magic link by token.
     */
    public function revoke(string $token): MagicLink
    {
        // Find the magic link by verifying the token against unused links only
        $link = MagicLink::findByToken($token, MagicLink::whereNull('used_at'));

        if ($link instanceof MagicLink) {
            $link->revoke();

            return $link;
        }

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Magic link not found or already used');
    }

    /**
     * Resolve the email address of a notifiable recipient.
     */
    protected function resolveEmail(object $notifiable): string
    {
        $candidates = [];

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('mail');

            // A mail route may be ['address' => 'Name'] or ['address', ...]
            if (is_array($route)) {
                $first = array_key_first($route);
                $route = is_string($first) ? $first : ($route[$first] ?? null);
            }

            $candidates[] = $route;
        }

        $field = config('oneclicklogin.email_field', 'email');
        $candidates[] = data_get($notifiable, is_string($field) && $field !== '' ? $field : 'email');
        $candidates[] = data_get($notifiable, 'email');

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException('Cannot resolve an email address for the magic link recipient ['.$notifiable::class.'].');
    }
}
