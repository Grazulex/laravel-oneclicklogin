<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Notifications;

/**
 * Delivers a magic link by SMS (default `notifications.sms` class).
 *
 * The message is sent through the notification channel set in `sms_channel`
 * (`vonage` by default). The SMS channel itself is not shipped: install the
 * one you use (e.g. laravel/vonage-notification-channel or
 * laravel-notification-channels/twilio), whose channel accepts the plain text
 * returned by `toVonage()` / `toTwilio()`. Custom channels can call `toSms()`.
 */
class MagicLinkSms extends MagicLinkNotification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channel = config('oneclicklogin.sms_channel', 'vonage');

        return [is_string($channel) && $channel !== '' ? $channel : 'vonage'];
    }

    /**
     * Text of the SMS.
     */
    public function content(): string
    {
        $replace = [
            'app' => $this->appName(),
            'minutes' => $this->minutesRemaining(),
            'url' => $this->url,
        ];

        $text = __(':app login link (valid :minutes min, single use): :url', $replace);

        return is_string($text) ? $text : "{$replace['app']} login link: {$this->url}";
    }

    public function toSms(object $notifiable): string
    {
        return $this->content();
    }

    public function toVonage(object $notifiable): string
    {
        return $this->content();
    }

    public function toTwilio(object $notifiable): string
    {
        return $this->content();
    }
}
