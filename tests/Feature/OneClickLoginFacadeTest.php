<?php

declare(strict_types=1);

use Grazulex\OneClickLogin\Facades\OneClickLogin;

it('provides access to MagicLinkManager through facade', function (): void {
    expect(OneClickLogin::getFacadeRoot())
        ->toBeInstanceOf(Grazulex\OneClickLogin\Services\MagicLinkManager::class);
});

it('can create magic links through facade', function (): void {
    $link = OneClickLogin::for('test@example.com')
        ->redirectTo('/dashboard')
        ->expiresInMinutes(30)
        ->generate();

    expect($link)->toBeInstanceOf(Grazulex\OneClickLogin\Models\MagicLink::class);
    expect($link->email)->toBe('test@example.com');
    expect($link->redirect_url)->toBe('/dashboard');
});

it('can consume magic links through facade', function (): void {
    $link = OneClickLogin::for('test@example.com')->generate();

    $consumer = OneClickLogin::consume($link->token);

    expect($consumer->isValid())->toBeTrue();
});

it('can prune expired magic links through facade', function (): void {
    // Create an expired magic link
    $expiredLink = OneClickLogin::for('test@example.com')
        ->expiresInMinutes(-60) // Expired 1 hour ago
        ->generate();

    // Create a valid magic link
    $validLink = OneClickLogin::for('test2@example.com')->generate();

    // Prune with 0 days to remove all expired links regardless of age
    $prunedCount = OneClickLogin::prune(0);

    expect($prunedCount)->toBe(1);

    // Verify the expired link was deleted and valid link remains
    expect(Grazulex\OneClickLogin\Models\MagicLink::find($expiredLink->id))->toBeNull();
    expect(Grazulex\OneClickLogin\Models\MagicLink::find($validLink->id))->not()->toBeNull();
});

it('can extend magic links through facade', function (): void {
    $link = OneClickLogin::for('test@example.com')
        ->expiresInMinutes(30)
        ->generate();

    $originalExpiry = $link->expires_at;

    OneClickLogin::extend($link->token, 60);

    $link->refresh();
    expect($link->expires_at->isAfter($originalExpiry))->toBeTrue();
});

it('can revoke magic links through facade', function (): void {
    $link = OneClickLogin::for('test@example.com')->generate();

    OneClickLogin::revoke($link->token);

    $link->refresh();
    expect($link->isUsed())->toBeTrue();
});
