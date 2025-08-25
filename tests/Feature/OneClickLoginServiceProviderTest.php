<?php

declare(strict_types=1);

use Grazulex\OneClickLogin\OneClickLoginServiceProvider;

it('can load the service provider', function () {
    $provider = new OneClickLoginServiceProvider($this->app);

    expect($provider)->toBeInstanceOf(OneClickLoginServiceProvider::class);
});

it('registers the configuration correctly', function () {
    expect(config('oneclicklogin'))->toBeArray();
    expect(config('oneclicklogin.ttl_minutes'))->toBe(15);
    expect(config('oneclicklogin.max_uses'))->toBe(1);
    expect(config('oneclicklogin.guard'))->toBe('web');
    expect(config('oneclicklogin.signed_route_name'))->toBe('oneclicklogin.consume');
});

it('has correct default configuration values', function () {
    expect(config('oneclicklogin.ip_binding'))->toBeFalse();
    expect(config('oneclicklogin.device_binding'))->toBeFalse();
    expect(config('oneclicklogin.enable_otp_step_up'))->toBeFalse();
    expect(config('oneclicklogin.redirect_after_login'))->toBe('/');
    expect(config('oneclicklogin.redirect_on_invalid'))->toBe('/login?invalid=1');
});

it('has correct multi persona configuration', function () {
    expect(config('oneclicklogin.multi_persona.enabled'))->toBeTrue();
    expect(config('oneclicklogin.multi_persona.keys'))->toBe(['persona', 'tenant', 'role']);
});

it('has correct rate limit configuration', function () {
    expect(config('oneclicklogin.rate_limit.issue_per_email_per_hour'))->toBe(5);
    expect(config('oneclicklogin.rate_limit.consume_per_ip_per_min'))->toBe(20);
});

it('has correct route configuration', function () {
    expect(config('oneclicklogin.route.prefix'))->toBe('login');
    expect(config('oneclicklogin.route.middleware'))->toBeArray();
});

it('has correct observability configuration', function () {
    expect(config('oneclicklogin.observability.enabled'))->toBeTrue();
    expect(config('oneclicklogin.observability.log'))->toBeTrue();
    expect(config('oneclicklogin.observability.metrics'))->toBeFalse();
});

it('has correct sharelink integration configuration', function () {
    expect(config('oneclicklogin.sharelink.enabled'))->toBeFalse();
    expect(config('oneclicklogin.sharelink.analytics'))->toBeTrue();
    expect(config('oneclicklogin.sharelink.audit_trails'))->toBeTrue();
});
