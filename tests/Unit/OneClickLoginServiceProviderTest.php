<?php

declare(strict_types=1);

use Grazulex\OneClickLogin\OneClickLoginServiceProvider;
use Illuminate\Support\ServiceProvider;

it('extends Laravel service provider', function () {
    $provider = new OneClickLoginServiceProvider($this->app);

    expect($provider)->toBeInstanceOf(ServiceProvider::class);
});

it('has register method', function () {
    $provider = new OneClickLoginServiceProvider($this->app);

    expect(method_exists($provider, 'register'))->toBeTrue();
});

it('has boot method', function () {
    $provider = new OneClickLoginServiceProvider($this->app);

    expect(method_exists($provider, 'boot'))->toBeTrue();
});
it('merges configuration correctly in register method', function () {
    // Create a mock app to verify the merge call
    $app = $this->app;
    $provider = new OneClickLoginServiceProvider($app);

    // Call register method
    $provider->register();

    // Verify config was merged
    expect(config('oneclicklogin'))->toBeArray()
        ->and(config('oneclicklogin'))->not()->toBeEmpty();
});

it('publishes configuration correctly in boot method', function () {
    $provider = new OneClickLoginServiceProvider($this->app);

    // Call boot method
    $provider->boot();

    // Check that publishable paths are registered
    $publishables = $provider::$publishGroups['oneclicklogin-config'] ?? [];

    expect($publishables)->toBeArray()
        ->and($publishables)->not()->toBeEmpty();
});

it('has correct namespace', function () {
    $provider = new OneClickLoginServiceProvider($this->app);

    expect(get_class($provider))->toBe('Grazulex\OneClickLogin\OneClickLoginServiceProvider');
});

it('can be instantiated', function () {
    expect(fn () => new OneClickLoginServiceProvider($this->app))
        ->not()->toThrow(Exception::class);
});

it('register method does not throw exceptions', function () {
    $provider = new OneClickLoginServiceProvider($this->app);

    expect(fn () => $provider->register())
        ->not()->toThrow(Exception::class);
});

it('boot method does not throw exceptions', function () {
    $provider = new OneClickLoginServiceProvider($this->app);

    expect(fn () => $provider->boot())
        ->not()->toThrow(Exception::class);
});
