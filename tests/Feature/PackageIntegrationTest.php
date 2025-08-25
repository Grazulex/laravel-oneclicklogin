<?php

declare(strict_types=1);

it('package is properly registered with Laravel', function () {
    $providers = $this->app->getLoadedProviders();

    expect($providers)->toHaveKey('Grazulex\OneClickLogin\OneClickLoginServiceProvider');
});

it('configuration is accessible via config helper', function () {
    expect(config('oneclicklogin'))->not()->toBeNull()
        ->and(config('oneclicklogin.ttl_minutes'))->not()->toBeNull()
        ->and(config('oneclicklogin.max_uses'))->not()->toBeNull();
});

it('can access nested configuration values', function () {
    expect(config('oneclicklogin.multi_persona.enabled'))->not()->toBeNull()
        ->and(config('oneclicklogin.rate_limit.issue_per_email_per_hour'))->not()->toBeNull()
        ->and(config('oneclicklogin.observability.enabled'))->not()->toBeNull();
});

it('configuration values have correct types', function () {
    expect(config('oneclicklogin.ttl_minutes'))->toBeInt()
        ->and(config('oneclicklogin.max_uses'))->toBeInt()
        ->and(config('oneclicklogin.guard'))->toBeString()
        ->and(config('oneclicklogin.ip_binding'))->toBeBool()
        ->and(config('oneclicklogin.multi_persona.enabled'))->toBeBool()
        ->and(config('oneclicklogin.multi_persona.keys'))->toBeArray();
});

it('can modify configuration at runtime', function () {
    $originalTtl = config('oneclicklogin.ttl_minutes');

    config(['oneclicklogin.ttl_minutes' => 30]);

    expect(config('oneclicklogin.ttl_minutes'))->toBe(30)
        ->and(config('oneclicklogin.ttl_minutes'))->not()->toBe($originalTtl);
});

it('package works in Laravel application context', function () {
    expect($this->app)->toBeInstanceOf(Illuminate\Foundation\Application::class)
        ->and($this->app->bound('config'))->toBeTrue()
        ->and($this->app['config']->get('oneclicklogin'))->not()->toBeNull();
});

it('package configuration does not conflict with Laravel core', function () {
    // Ensure our config doesn't override Laravel core configs
    expect(config('app.name'))->not()->toBeNull()
        ->and(config('database.default'))->not()->toBeNull()
        ->and(config('cache.default'))->not()->toBeNull();
});

it('can use config in different Laravel environments', function () {
    // Test in testing environment
    $this->app['env'] = 'testing';
    expect(config('oneclicklogin.ttl_minutes'))->toBe(15);

    // Test environment change doesn't break config
    $this->app['env'] = 'local';
    expect(config('oneclicklogin.ttl_minutes'))->toBe(15);
});
