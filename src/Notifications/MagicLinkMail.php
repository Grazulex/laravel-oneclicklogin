<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Delivers a magic link by email (default `notifications.mail` class).
 */
class MagicLinkMail extends MagicLinkNotification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = $this->appName();

        return (new MailMessage())
            ->subject(__('Your login link for :app', ['app' => $app]))
            ->line(__('Click the button below to log in to :app.', ['app' => $app]))
            ->action(__('Log in'), $this->url)
            ->line(__('This link expires in :minutes minutes and can only be used once.', ['minutes' => $this->minutesRemaining()]))
            ->line(__('If you did not request this link, you can safely ignore this email.'));
    }

    /**
     * Representation for array-based channels (e.g. database).
     * The URL is left out on purpose: it grants access to the account.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'email' => $this->link->email,
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }
}
