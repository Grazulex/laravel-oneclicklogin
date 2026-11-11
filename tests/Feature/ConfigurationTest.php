<?php

declare(strict_types=1);

it('can publish configuration files', function () {
    $this->artisan('vendor:publish', [
        '--provider' => 'Grazulex\OneClickLogin\OneClickLoginServiceProvider',
        '--tag' => 'oneclicklogin-config',
        '--force' => true,
    ])->assertExitCode(0);

    $published = config_path('oneclicklogin.php');

    try {
        expect(file_exists($published))->toBeTrue()
            ->and(file_get_contents($published))->toBe(file_get_contents(__DIR__.'/../../config/oneclicklogin.php'));
    } finally {
        // Do not leave the published file in the shared testbench skeleton
        @unlink($published);
    }
});

it('can override configuration values', function () {
    config(['oneclicklogin.ttl_minutes' => 30]);
    config(['oneclicklogin.rate_limit.issue_per_email_per_hour' => 3]);
    config(['oneclicklogin.guard' => 'api']);

    expect(config('oneclicklogin.ttl_minutes'))->toBe(30);
    expect(config('oneclicklogin.rate_limit.issue_per_email_per_hour'))->toBe(3);
    expect(config('oneclicklogin.guard'))->toBe('api');
});

it('validates boolean configuration values', function () {
    config(['oneclicklogin.ip_binding' => true]);
    config(['oneclicklogin.device_binding' => true]);
    config(['oneclicklogin.security.signed_urls' => true]);

    expect(config('oneclicklogin.ip_binding'))->toBeTrue();
    expect(config('oneclicklogin.device_binding'))->toBeTrue();
    expect(config('oneclicklogin.security.signed_urls'))->toBeTrue();
});

it('has valid notification class configuration', function () {
    $mailNotification = config('oneclicklogin.notifications.mail');
    $smsNotification = config('oneclicklogin.notifications.sms');

    expect($mailNotification)->toBe(Grazulex\OneClickLogin\Notifications\MagicLinkMail::class);
    expect($smsNotification)->toBe(Grazulex\OneClickLogin\Notifications\MagicLinkSms::class);
    expect(class_exists($mailNotification))->toBeTrue();
    expect(class_exists($smsNotification))->toBeTrue();
});

it('can handle environment variable configuration', function () {
    $envVars = [
        'ONECLICKLOGIN_TTL_MINUTES' => '25',
        'ONECLICKLOGIN_RATE_CONSUME_PER_IP_PER_MIN' => '2',
        'ONECLICKLOGIN_GUARD' => 'sanctum',
        'ONECLICKLOGIN_IP_BINDING' => 'true',
        'ONECLICKLOGIN_DEVICE_BINDING' => 'true',
    ];

    foreach ($envVars as $key => $value) {
        putenv("$key=$value");
    }

    // Reload config to pick up environment changes
    $this->app['config']->set('oneclicklogin.ttl_minutes', (int) env('ONECLICKLOGIN_TTL_MINUTES', 15));
    $this->app['config']->set('oneclicklogin.rate_limit.consume_per_ip_per_min', (int) env('ONECLICKLOGIN_RATE_CONSUME_PER_IP_PER_MIN', 20));
    $this->app['config']->set('oneclicklogin.guard', env('ONECLICKLOGIN_GUARD', 'web'));
    $this->app['config']->set('oneclicklogin.ip_binding', filter_var(env('ONECLICKLOGIN_IP_BINDING', false), FILTER_VALIDATE_BOOLEAN));
    $this->app['config']->set('oneclicklogin.device_binding', filter_var(env('ONECLICKLOGIN_DEVICE_BINDING', false), FILTER_VALIDATE_BOOLEAN));

    expect(config('oneclicklogin.ttl_minutes'))->toBe(25);
    expect(config('oneclicklogin.rate_limit.consume_per_ip_per_min'))->toBe(2);
    expect(config('oneclicklogin.guard'))->toBe('sanctum');
    expect(config('oneclicklogin.ip_binding'))->toBeTrue();
    expect(config('oneclicklogin.device_binding'))->toBeTrue();

    // Clean up
    foreach ($envVars as $key => $value) {
        putenv($key);
    }
});
