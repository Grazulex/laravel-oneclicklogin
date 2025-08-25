<?php

declare(strict_types=1);

it('can create magic link via manager', function (): void {
    $manager = app(Grazulex\OneClickLogin\Services\MagicLinkManager::class);

    $link = $manager->for('test@example.com')
        ->redirectTo('/dashboard')
        ->expiresInMinutes(30)
        ->generate();

    expect($link)->toBeInstanceOf(Grazulex\OneClickLogin\Models\MagicLink::class)
        ->and($link->email)->toBe('test@example.com')
        ->and($link->redirect_url)->toBe('/dashboard')
        ->and($link->token)->toBeString()
        ->and($link->token_hash)->toBeString()
        ->and($link->ulid)->toBeString();
});

it('can validate magic link via consumer', function (): void {
    $manager = app(Grazulex\OneClickLogin\Services\MagicLinkManager::class);

    $link = $manager->for('test@example.com')
        ->redirectTo('/dashboard')
        ->generate();

    $consumer = $manager->consume($link->token);

    expect($consumer->isValid())->toBeTrue()
        ->and($consumer->canUse())->toBeTrue();
});
