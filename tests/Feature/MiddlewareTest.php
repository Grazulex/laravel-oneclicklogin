<?php

declare(strict_types=1);

use Grazulex\OneClickLogin\Events\MagicLinkAttempt;
use Grazulex\OneClickLogin\Facades\OneClickLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    RateLimiter::clear('*');
    Event::fake();
    Log::spy();
});

it('applies rate limiting to magic link verification', function (): void {
    $user = createTestUser('test@example.com');

    // Make multiple requests quickly
    for ($i = 0; $i < 12; $i++) {
        $response = $this->get('/magic-link/verify/invalidtoken123');

        if ($i < 10) {
            // First 10 requests should be processed normally
            $response->assertRedirect('/login');
        } else {
            // 11th and 12th requests should be rate limited
            $response->assertStatus(429)
                ->assertJson([
                    'success' => false,
                ]);
            
            // Check that the message contains the rate limit text (timing may vary slightly)
            $responseData = $response->json();
            expect($responseData['message'])->toContain('Too many attempts. Please try again in');
            expect($responseData['message'])->toContain('seconds.');
            expect($responseData)->toHaveKey('retry_after');
            expect($responseData['retry_after'])->toBeGreaterThan(0);
            expect($responseData['retry_after'])->toBeLessThanOrEqual(300);
        }
    }
});

it('logs magic link verification attempts', function (): void {
    $user = createTestUser('test@example.com');

    $link = OneClickLogin::for('test@example.com')
        ->redirectTo('/dashboard')
        ->generate();

    // Make a successful request
    $this->get("/magic-link/verify/{$link->token}");

    // Verify logging was called
    Log::shouldHaveReceived('info')
        ->with('Magic link verification successful', Mockery::on(function ($data) {
            return is_array($data)
                && $data['success'] === true
                && isset($data['token'])
                && isset($data['ip'])
                && isset($data['duration_ms']);
        }));

    // Verify event was fired
    Event::assertDispatched(MagicLinkAttempt::class, function (MagicLinkAttempt $event) {
        return $event->success === true &&
               $event->ip !== null &&
               $event->timestamp !== null;
    });
});

it('logs failed magic link verification attempts', function (): void {
    // Make a failed request
    $this->get('/magic-link/verify/invalidtoken456');

    // Verify warning was logged
    Log::shouldHaveReceived('warning')
        ->with('Magic link verification failed', Mockery::on(function ($data) {
            return is_array($data)
                && $data['success'] === false
                && isset($data['token'])
                && isset($data['ip']);
        }));

    // Verify event was fired
    Event::assertDispatched(MagicLinkAttempt::class, function (MagicLinkAttempt $event) {
        return $event->success === false;
    });
});

it('clears rate limiting on successful authentication', function (): void {
    $user = createTestUser('test@example.com');

    // Make some failed attempts first
    for ($i = 0; $i < 5; $i++) {
        $this->get('/magic-link/verify/invalidtoken789');
    }

    // Now make a successful request
    $link = OneClickLogin::for('test@example.com')
        ->redirectTo('/dashboard')
        ->generate();

    $response = $this->get("/magic-link/verify/{$link->token}");
    $response->assertRedirect('/dashboard');

    // Should be able to make more requests without being rate limited
    for ($i = 0; $i < 8; $i++) {
        $response = $this->get('/magic-link/verify/anotherinvalidtoken123');
        $response->assertRedirect('/login'); // Should not be rate limited
    }
});
