<?php

declare(strict_types=1);

use Grazulex\OneClickLogin\Facades\OneClickLogin;
use Grazulex\OneClickLogin\Models\MagicLink;
use Grazulex\OneClickLogin\Notifications\MagicLinkMail;
use Grazulex\OneClickLogin\Notifications\MagicLinkSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
});

function createDeliveryUser(string $email = 'jane@example.com'): DeliveryTestUser
{
    return DeliveryTestUser::create([
        'email' => $email,
        'name' => 'Jane',
        'password' => bcrypt('password'),
    ]);
}

it('emails the link to the builder address by default', function (): void {
    $link = OneClickLogin::for('jane@example.com')->redirectTo('/profile')->send();

    expect($link)->toBeInstanceOf(MagicLink::class)
        ->and($link->exists)->toBeTrue()
        ->and($link->redirect_url)->toBe('/profile');

    Notification::assertSentOnDemand(
        MagicLinkMail::class,
        fn (MagicLinkMail $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'jane@example.com'
            && $channels === ['mail']
            && $notification->url === $link->url
            && $notification->link->is($link)
    );
});

it('notifies a notifiable recipient set with to()', function (): void {
    $user = createDeliveryUser();

    $link = OneClickLogin::to($user)->via('mail')->send();

    expect($link->email)->toBe('jane@example.com');

    Notification::assertSentTo(
        $user,
        MagicLinkMail::class,
        fn (MagicLinkMail $notification): bool => $notification->url === $link->url
    );
});

it('can attach a recipient to a builder created with for()', function (): void {
    $user = createDeliveryUser();

    OneClickLogin::for('jane@example.com')->to($user)->send();

    Notification::assertSentTo($user, MagicLinkMail::class);
    Notification::assertSentOnDemandTimes(MagicLinkMail::class, 0);
});

it('delivers the link by sms through the configured channel', function (): void {
    config(['oneclicklogin.sms_channel' => 'twilio']);
    $user = createDeliveryUser();

    $link = OneClickLogin::to($user)->via('sms')->send();

    Notification::assertSentTo(
        $user,
        MagicLinkSms::class,
        fn (MagicLinkSms $notification, array $channels): bool => $channels === ['twilio']
            && str_contains($notification->toSms($user), $link->url)
            && $notification->toVonage($user) === $notification->toTwilio($user)
    );
    Notification::assertNotSentTo($user, MagicLinkMail::class);
});

it('delivers through several channels', function (): void {
    $user = createDeliveryUser();

    OneClickLogin::to($user)->via(['mail', 'sms'])->send();

    Notification::assertSentTo($user, MagicLinkMail::class);
    Notification::assertSentTo($user, MagicLinkSms::class);
    expect(MagicLink::count())->toBe(1);
});

it('requires a notifiable recipient for channels other than mail', function (): void {
    expect(fn () => OneClickLogin::for('jane@example.com')->via('sms')->send())
        ->toThrow(InvalidArgumentException::class, 'requires a notifiable recipient');

    expect(MagicLink::count())->toBe(0);
    Notification::assertNothingSent();
});

it('uses the notification class configured for the channel', function (): void {
    config(['oneclicklogin.notifications.mail' => DeliveryTestMailNotification::class]);

    OneClickLogin::for('jane@example.com')->send();

    Notification::assertSentOnDemand(DeliveryTestMailNotification::class);
});

it('falls back to the package notification when the configured class does not exist', function (): void {
    config(['oneclicklogin.notifications.mail' => 'App\Notifications\MagicLinkMail']);

    OneClickLogin::for('jane@example.com')->send();

    Notification::assertSentOnDemand(MagicLinkMail::class);
});

it('rejects a configured class that is not a notification', function (): void {
    config(['oneclicklogin.notifications.mail' => stdClass::class]);

    expect(fn () => OneClickLogin::for('jane@example.com')->send())
        ->toThrow(InvalidArgumentException::class, 'must extend');

    expect(MagicLink::count())->toBe(0);
});

it('rejects an unknown delivery channel', function (): void {
    $user = createDeliveryUser();

    expect(fn () => OneClickLogin::to($user)->via('pigeon')->send())
        ->toThrow(InvalidArgumentException::class, 'No notification class configured for the [pigeon] delivery channel');

    expect(MagicLink::count())->toBe(0);
});

it('supports custom channels declared in the notifications configuration', function (): void {
    config(['oneclicklogin.notifications.custom' => DeliveryTestMailNotification::class]);
    $user = createDeliveryUser();

    OneClickLogin::to($user)->via('custom')->send();

    Notification::assertSentTo($user, DeliveryTestMailNotification::class);
});

it('requires at least one channel', function (): void {
    expect(fn () => OneClickLogin::for('jane@example.com')->via([]))
        ->toThrow(InvalidArgumentException::class);
});

it('throws when no email address can be resolved for the recipient', function (): void {
    expect(fn () => OneClickLogin::to(new stdClass()))
        ->toThrow(InvalidArgumentException::class, 'Cannot resolve an email address');
});

it('resolves the email of a recipient without the Notifiable trait', function (): void {
    $recipient = new stdClass();
    $recipient->email = 'plain@example.com';

    $link = OneClickLogin::to($recipient)->send();

    expect($link->email)->toBe('plain@example.com');
    Notification::assertSentOnDemand(
        MagicLinkMail::class,
        fn (MagicLinkMail $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'plain@example.com'
    );
});

it('does not send anything when the rate limit is exceeded', function (): void {
    config(['oneclicklogin.rate_limit.issue_per_email_per_hour' => 1]);

    OneClickLogin::for('jane@example.com')->send();

    expect(fn () => OneClickLogin::for('jane@example.com')->send())
        ->toThrow(Exception::class, 'Rate limit exceeded');

    Notification::assertSentOnDemandTimes(MagicLinkMail::class, 1);
});

it('builds the mail message with the link', function (): void {
    config(['app.name' => 'Acme']);
    $link = OneClickLogin::for('jane@example.com')->expiresInMinutes(30)->generate();

    $notification = new MagicLinkMail($link);
    $message = $notification->toMail(new AnonymousNotifiable());

    expect($notification->via(new AnonymousNotifiable()))->toBe(['mail'])
        ->and($message->actionUrl)->toBe($link->url)
        ->and($message->subject)->toContain('Acme')
        ->and(implode(' ', $message->outroLines))->toContain('30 minutes')
        ->and($notification->toArray(new AnonymousNotifiable()))->toHaveKeys(['email', 'expires_at'])
        ->and($notification->toArray(new AnonymousNotifiable()))->not->toHaveKey('url');
});

it('uses vonage as default sms channel', function (): void {
    $link = OneClickLogin::for('jane@example.com')->generate();

    expect((new MagicLinkSms($link))->via(new AnonymousNotifiable()))->toBe(['vonage']);
});

it('emails the link from the generate command with --send', function (): void {
    $this->artisan('oneclicklogin:generate', [
        'email' => 'jane@example.com',
        '--send' => true,
    ])
        ->expectsOutput('Magic link generated for jane@example.com')
        ->expectsOutput('Magic link sent by email to jane@example.com')
        ->assertExitCode(0);

    Notification::assertSentOnDemand(
        MagicLinkMail::class,
        fn (MagicLinkMail $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'jane@example.com'
    );
});

it('does not send anything from the generate command without --send', function (): void {
    $this->artisan('oneclicklogin:generate', ['email' => 'jane@example.com'])
        ->assertExitCode(0);

    Notification::assertNothingSent();
    expect(MagicLink::count())->toBe(1);
});

class DeliveryTestUser extends TestUser
{
    use Notifiable;

    public function routeNotificationForVonage(): string
    {
        return '+32470000000';
    }

    public function routeNotificationForTwilio(): string
    {
        return '+32470000000';
    }
}

class DeliveryTestMailNotification extends MagicLinkMail {}
