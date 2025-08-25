<?php

declare(strict_types=1);

use Grazulex\OneClickLogin\Facades\OneClickLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can verify a valid magic link via HTTP', function (): void {
    // Create a test user
    $user = createTestUser('test@example.com');

    // Generate a magic link
    $link = OneClickLogin::for('test@example.com')
        ->redirectTo('/dashboard')
        ->generate();

    // Verify the magic link via HTTP
    $response = $this->get("/magic-link/verify/{$link->token}");

    $response->assertRedirect('/dashboard');

    // Check that user is authenticated
    $this->assertAuthenticated();

    // Check that the magic link was consumed
    $link->refresh();
    expect($link->isUsed())->toBeTrue();
});

it('returns error for invalid magic link via HTTP', function (): void {
    $response = $this->get('/magic-link/verify/invalidtoken123');

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors(['magic_link']);

    $this->assertGuest();
});

it('returns error for used magic link via HTTP', function (): void {
    // Create a test user
    $user = createTestUser('test@example.com');

    // Generate and consume a magic link
    $link = OneClickLogin::for('test@example.com')
        ->generate();
    $link->markAsUsed();

    // Try to use it again
    $response = $this->get("/magic-link/verify/{$link->token}");

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors(['magic_link']);

    $this->assertGuest();
});

it('can handle JSON requests for magic link verification', function (): void {
    // Create a test user
    $user = createTestUser('test@example.com');

    // Generate a magic link
    $link = OneClickLogin::for('test@example.com')
        ->redirectTo('/dashboard')
        ->generate();

    // Make JSON request
    $response = $this->getJson("/magic-link/verify/{$link->token}");

    $response->assertJson([
        'success' => true,
        'message' => 'Successfully authenticated',
        'redirect_url' => '/dashboard',
    ]);

    $this->assertAuthenticated();
});

it('handles unknown users when allowed', function (): void {
    config(['oneclicklogin.allow_unknown_users' => true]);

    // Generate magic link for unknown user
    $link = OneClickLogin::for('unknown@example.com')
        ->redirectTo('/dashboard')
        ->generate();

    $response = $this->get("/magic-link/verify/{$link->token}");

    $response->assertRedirect('/register');
    $response->assertSessionHas('magic_link_email', 'unknown@example.com');
});
