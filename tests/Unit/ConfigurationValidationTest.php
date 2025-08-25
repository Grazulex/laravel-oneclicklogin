<?php

declare(strict_types=1);

it('has valid default TTL configuration', function () {
    $ttl = config('oneclicklogin.ttl_minutes');

    expect($ttl)->toBeInt()
        ->and($ttl)->toBeGreaterThan(0)
        ->and($ttl)->toBeLessThanOrEqual(60); // Max 1 hour for security
});

it('has valid max uses configuration', function () {
    $maxUses = config('oneclicklogin.max_uses');

    expect($maxUses)->toBeInt()
        ->and($maxUses)->toBeGreaterThan(0)
        ->and($maxUses)->toBeLessThanOrEqual(10); // Reasonable limit
});

it('has valid guard configuration', function () {
    $guard = config('oneclicklogin.guard');

    expect($guard)->toBeString()
        ->and($guard)->not()->toBeEmpty();
});

it('has valid route name configuration', function () {
    $routeName = config('oneclicklogin.signed_route_name');

    expect($routeName)->toBeString()
        ->and($routeName)->toMatch('/^[a-zA-Z0-9._-]+$/')
        ->and($routeName)->toContain('oneclicklogin');
});

it('has valid redirect URLs configuration', function () {
    $afterLogin = config('oneclicklogin.redirect_after_login');
    $onInvalid = config('oneclicklogin.redirect_on_invalid');

    expect($afterLogin)->toBeString()
        ->and($afterLogin)->toStartWith('/')
        ->and($onInvalid)->toBeString()
        ->and($onInvalid)->toStartWith('/');
});

it('has valid rate limit configuration values', function () {
    $issueLimit = config('oneclicklogin.rate_limit.issue_per_email_per_hour');
    $consumeLimit = config('oneclicklogin.rate_limit.consume_per_ip_per_min');

    expect($issueLimit)->toBeInt()
        ->and($issueLimit)->toBeGreaterThan(0)
        ->and($issueLimit)->toBeLessThanOrEqual(100)
        ->and($consumeLimit)->toBeInt()
        ->and($consumeLimit)->toBeGreaterThan(0)
        ->and($consumeLimit)->toBeLessThanOrEqual(1000);
});

it('has valid multi persona keys configuration', function () {
    $keys = config('oneclicklogin.multi_persona.keys');

    expect($keys)->toBeArray()
        ->and($keys)->not()->toBeEmpty()
        ->and($keys)->toContain('persona')
        ->and($keys)->toContain('tenant')
        ->and($keys)->toContain('role');

    foreach ($keys as $key) {
        expect($key)->toBeString()
            ->and($key)->not()->toBeEmpty()
            ->and($key)->toMatch('/^[a-zA-Z_][a-zA-Z0-9_]*$/');
    }
});

it('has valid middleware configuration', function () {
    $middleware = config('oneclicklogin.route.middleware');

    expect($middleware)->toBeArray();

    foreach ($middleware as $middlewareClass) {
        expect($middlewareClass)->toBeString()
            ->and($middlewareClass)->not()->toBeEmpty();
    }
});

it('has valid boolean configuration flags', function () {
    $booleanConfigs = [
        'ip_binding',
        'device_binding',
        'enable_otp_step_up',
        'multi_persona.enabled',
        'observability.enabled',
        'observability.log',
        'observability.metrics',
        'sharelink.enabled',
        'sharelink.analytics',
        'sharelink.audit_trails',
    ];

    foreach ($booleanConfigs as $configKey) {
        $value = config("oneclicklogin.{$configKey}");
        expect($value)->toBeBool();
    }
});
