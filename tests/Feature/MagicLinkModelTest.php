<?php

declare(strict_types=1);

use Grazulex\OneClickLogin\Models\MagicLink;

it('can create magic link directly', function (): void {
    $link = new MagicLink();
    $link->email = 'test@example.com';
    $link->redirect_url = '/dashboard';
    $link->expires_at = now()->addMinutes(30);
    $link->context = [];
    $link->meta = [];
    $link->generateToken();

    $link->save();

    expect($link->exists)->toBeTrue()
        ->and($link->email)->toBe('test@example.com')
        ->and($link->token)->toBeString()
        ->and($link->token_hash)->toBeString();
});
