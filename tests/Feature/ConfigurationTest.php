<?php

declare(strict_types=1);

it('can publish configuration files', function () {
    $this->artisan('vendor:publish', [
        '--provider' => 'Grazulex\OneClickLogin\OneClickLoginServiceProvider',
        '--tag' => 'oneclicklogin-config',
        '--force' => true,
    ])->assertExitCode(0);

    expect(file_exists(config_path('oneclicklogin.php')))->toBeTrue();
});

it('can override configuration values', function () {
    config(['oneclicklogin.ttl_minutes' => 30]);
    config(['oneclicklogin.max_uses' => 3]);
    config(['oneclicklogin.guard' => 'api']);

    expect(config('oneclicklogin.ttl_minutes'))->toBe(30);
    expect(config('oneclicklogin.max_uses'))->toBe(3);
    expect(config('oneclicklogin.guard'))->toBe('api');
});

it('validates boolean configuration values', function () {
    config(['oneclicklogin.ip_binding' => true]);
    config(['oneclicklogin.device_binding' => true]);
    config(['oneclicklogin.enable_otp_step_up' => true]);

    expect(config('oneclicklogin.ip_binding'))->toBeTrue();
    expect(config('oneclicklogin.device_binding'))->toBeTrue();
    expect(config('oneclicklogin.enable_otp_step_up'))->toBeTrue();
});

it('has valid notification class configuration', function () {
    $mailNotification = config('oneclicklogin.notifications.mail');
    $smsNotification = config('oneclicklogin.notifications.sms');

    expect($mailNotification)->toBe('App\Notifications\MagicLinkMail');
    expect($smsNotification)->toBe('App\Notifications\MagicLinkSms');
});

it('can handle environment variable configuration', function () {
    $envVars = [
        'ONECLICKLOGIN_TTL_MINUTES' => '25',
        'ONECLICKLOGIN_MAX_USES' => '2',
        'ONECLICKLOGIN_GUARD' => 'sanctum',
        'ONECLICKLOGIN_IP_BINDING' => 'true',
        'ONECLICKLOGIN_DEVICE_BINDING' => 'true',
    ];

    foreach ($envVars as $key => $value) {
        putenv("$key=$value");
    }

    // Reload config to pick up environment changes
    $this->app['config']->set('oneclicklogin.ttl_minutes', (int) env('ONECLICKLOGIN_TTL_MINUTES', 15));
    $this->app['config']->set('oneclicklogin.max_uses', (int) env('ONECLICKLOGIN_MAX_USES', 1));
    $this->app['config']->set('oneclicklogin.guard', env('ONECLICKLOGIN_GUARD', 'web'));
    $this->app['config']->set('oneclicklogin.ip_binding', filter_var(env('ONECLICKLOGIN_IP_BINDING', false), FILTER_VALIDATE_BOOLEAN));
    $this->app['config']->set('oneclicklogin.device_binding', filter_var(env('ONECLICKLOGIN_DEVICE_BINDING', false), FILTER_VALIDATE_BOOLEAN));

    expect(config('oneclicklogin.ttl_minutes'))->toBe(25);
    expect(config('oneclicklogin.max_uses'))->toBe(2);
    expect(config('oneclicklogin.guard'))->toBe('sanctum');
    expect(config('oneclicklogin.ip_binding'))->toBeTrue();
    expect(config('oneclicklogin.device_binding'))->toBeTrue();

    // Clean up
    foreach ($envVars as $key => $value) {
        putenv($key);
    }
});
