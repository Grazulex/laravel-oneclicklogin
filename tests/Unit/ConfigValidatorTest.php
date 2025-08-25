<?php

declare(strict_types=1);

use Grazulex\OneClickLogin\Support\ConfigValidator;

it('validates user model exists', function (): void {
    expect(fn () => ConfigValidator::validateUserModel('NonExistentModel'))
        ->toThrow(InvalidArgumentException::class, 'User model class [NonExistentModel] does not exist.');
});

it('validates expiration time bounds', function (): void {
    expect(fn () => ConfigValidator::validateExpirationTime(0))
        ->toThrow(InvalidArgumentException::class, 'Magic link expiration time must be at least 1 minute.');

    expect(fn () => ConfigValidator::validateExpirationTime(10081))
        ->toThrow(InvalidArgumentException::class, 'Magic link expiration time cannot exceed 7 days');

    // Valid expiration times should not throw
    ConfigValidator::validateExpirationTime(60);
    ConfigValidator::validateExpirationTime(1440);
});

it('validates token length bounds', function (): void {
    expect(fn () => ConfigValidator::validateTokenLength(31))
        ->toThrow(InvalidArgumentException::class, 'Token length must be at least 32 characters');

    expect(fn () => ConfigValidator::validateTokenLength(256))
        ->toThrow(InvalidArgumentException::class, 'Token length cannot exceed 255 characters');

    // Valid token lengths should not throw
    ConfigValidator::validateTokenLength(32);
    ConfigValidator::validateTokenLength(64);
    ConfigValidator::validateTokenLength(255);
});

it('validates redirect URLs', function (): void {
    // Null URLs should be allowed
    ConfigValidator::validateRedirectUrl(null);

    // Relative URLs should be allowed
    ConfigValidator::validateRedirectUrl('/dashboard');
    ConfigValidator::validateRedirectUrl('/admin/users');

    // Valid absolute URLs should be allowed
    ConfigValidator::validateRedirectUrl('https://example.com/dashboard');
    ConfigValidator::validateRedirectUrl('http://localhost:3000/app');

    // Invalid URLs should throw
    expect(fn () => ConfigValidator::validateRedirectUrl('not-a-url'))
        ->toThrow(InvalidArgumentException::class, 'Invalid redirect URL');

    expect(fn () => ConfigValidator::validateRedirectUrl('ftp://example.com'))
        ->toThrow(InvalidArgumentException::class, 'Redirect URL must use http or https scheme');
});

it('validates redirect URLs with various formats', function (string $url, bool $shouldPass): void {
    if ($shouldPass) {
        ConfigValidator::validateRedirectUrl($url);
        expect(true)->toBeTrue(); // Test passes if no exception
    } else {
        expect(fn () => ConfigValidator::validateRedirectUrl($url))
            ->toThrow(InvalidArgumentException::class);
    }
})->with([
    ['/dashboard', true],
    ['/admin/users?tab=active', true],
    ['https://example.com', true],
    ['http://localhost:8000', true],
    ['javascript:alert(1)', false],
    ['data:text/html,<script>alert(1)</script>', false],
    ['//evil.com', false],
    ['not-a-url', false],
]);
