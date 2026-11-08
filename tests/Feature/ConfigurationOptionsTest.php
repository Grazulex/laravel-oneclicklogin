<?php

declare(strict_types=1);

use Grazulex\OneClickLogin\Facades\OneClickLogin;
use Grazulex\OneClickLogin\Models\MagicLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

// rate_limit.issue_per_email_per_hour

it('limits link generation using rate_limit.issue_per_email_per_hour', function (): void {
    config(['oneclicklogin.rate_limit.issue_per_email_per_hour' => 2]);

    OneClickLogin::for('limited@example.com')->generate();
    OneClickLogin::for('limited@example.com')->generate();

    expect(fn () => OneClickLogin::for('limited@example.com')->generate())
        ->toThrow(Exception::class, 'Rate limit exceeded for email: limited@example.com');

    // Other emails are not affected
    expect(OneClickLogin::for('other@example.com')->generate())->toBeInstanceOf(MagicLink::class);
});

it('disables link generation rate limiting when the limit is 0', function (): void {
    config(['oneclicklogin.rate_limit.issue_per_email_per_hour' => 0]);

    for ($i = 0; $i < 8; $i++) {
        OneClickLogin::for('unlimited@example.com')->generate();
    }

    expect(MagicLink::where('email', 'unlimited@example.com')->count())->toBe(8);
});

it('still honors the legacy rate_limiting keys', function (): void {
    config(['oneclicklogin.rate_limiting' => ['max_attempts' => 1]]);

    OneClickLogin::for('legacy@example.com')->generate();

    expect(fn () => OneClickLogin::for('legacy@example.com')->generate())
        ->toThrow(Exception::class, 'Rate limit exceeded');

    config(['oneclicklogin.rate_limiting' => ['enabled' => false, 'max_attempts' => 1]]);

    expect(OneClickLogin::for('legacy@example.com')->generate())->toBeInstanceOf(MagicLink::class);
});

// rate_limit.consume_per_ip_per_min

it('limits verification attempts using rate_limit.consume_per_ip_per_min', function (): void {
    config(['oneclicklogin.rate_limit.consume_per_ip_per_min' => 2]);

    $this->get('/magic-link/verify/invalidtoken1')->assertRedirect('/login?invalid=1');
    $this->get('/magic-link/verify/invalidtoken2')->assertRedirect('/login?invalid=1');

    $response = $this->get('/magic-link/verify/invalidtoken3');

    $response->assertStatus(429);
    expect($response->json('retry_after'))->toBeLessThanOrEqual(60);
});

// redirect_on_invalid

it('redirects rejected links to redirect_on_invalid', function (): void {
    config(['oneclicklogin.redirect_on_invalid' => '/oops']);

    $response = $this->get('/magic-link/verify/invalidtoken123');

    $response->assertRedirect('/oops');
    $response->assertSessionHasErrors(['magic_link']);
});

it('does not clear the verification rate limit on custom invalid redirects', function (): void {
    config([
        'oneclicklogin.redirect_on_invalid' => '/oops',
        'oneclicklogin.rate_limit.consume_per_ip_per_min' => 2,
    ]);

    $this->get('/magic-link/verify/invalidtoken1')->assertRedirect('/oops');
    $this->get('/magic-link/verify/invalidtoken2')->assertRedirect('/oops');
    $this->get('/magic-link/verify/invalidtoken3')->assertStatus(429);
});

// signed_route_name

it('builds link URLs from signed_route_name', function (): void {
    Route::get('/custom-magic/{token}', fn () => 'ok')->name('custom.magic');
    app('router')->getRoutes()->refreshNameLookups();

    config(['oneclicklogin.signed_route_name' => 'custom.magic']);

    $link = OneClickLogin::for('test@example.com')->generate();

    expect($link->url)->toBe(url('/custom-magic/'.$link->token));
});

it('falls back to the package route when signed_route_name does not exist', function (): void {
    config(['oneclicklogin.signed_route_name' => 'oneclicklogin.consume']);

    $link = OneClickLogin::for('test@example.com')->generate();

    expect($link->url)->toBe(url('/magic-link/verify/'.$link->token));
});

it('still honors the legacy route_name key', function (): void {
    Route::get('/legacy-magic/{token}', fn () => 'ok')->name('legacy.magic');
    app('router')->getRoutes()->refreshNameLookups();

    config(['oneclicklogin.route_name' => 'legacy.magic']);

    $link = OneClickLogin::for('test@example.com')->generate();

    expect($link->url)->toBe(url('/legacy-magic/'.$link->token));
});

// security.hash_algorithm

it('hashes tokens with sha256 by default', function (): void {
    $link = OneClickLogin::for('test@example.com')->generate();

    expect($link->token_hash)->toBe(hash('sha256', $link->token))
        ->and($link->verifyToken($link->token))->toBeTrue()
        ->and($link->verifyToken('wrong-token'))->toBeFalse();
});

it('hashes tokens with bcrypt when configured', function (): void {
    config(['oneclicklogin.security.hash_algorithm' => 'bcrypt']);

    $link = OneClickLogin::for('test@example.com')->generate();

    expect($link->token_hash)->toStartWith('$2y$')
        ->and(OneClickLogin::consume($link->token)->isValid())->toBeTrue();
});

it('rejects unsupported hash algorithms', function (): void {
    config(['oneclicklogin.security.hash_algorithm' => 'md5']);

    expect(fn () => OneClickLogin::for('test@example.com')->generate())
        ->toThrow(InvalidArgumentException::class, 'Unsupported magic link hash algorithm');
});

it('still accepts links hashed by previous versions', function (): void {
    createTestUser('legacy@example.com');

    $link = MagicLink::create([
        'email' => 'legacy@example.com',
        'token_hash' => Hash::make('legacytoken123'),
        'redirect_url' => '/dashboard',
        'expires_at' => now()->addMinutes(15),
    ]);

    expect(OneClickLogin::consume('legacytoken123')->getMagicLink()?->id)->toBe($link->id);

    $this->get('/magic-link/verify/legacytoken123')->assertRedirect('/dashboard');
    $this->assertAuthenticated();
});

// security.signed_urls

it('generates and enforces signed URLs when signed_urls is enabled', function (): void {
    config(['oneclicklogin.security.signed_urls' => true]);
    createTestUser('test@example.com');

    $link = OneClickLogin::for('test@example.com')->redirectTo('/dashboard')->generate();

    expect($link->url)->toContain('signature=')->toContain('expires=');

    // Unsigned URL is rejected
    $this->get('/magic-link/verify/'.$link->token)->assertRedirect('/login?invalid=1');
    $this->assertGuest();

    // Tampered signature is rejected
    $this->getJson($link->url.'x')->assertStatus(403);
    $this->assertGuest();

    // Signed URL is accepted
    $this->get($link->url)->assertRedirect('/dashboard');
    $this->assertAuthenticated();
});

it('does not sign URLs when signed_urls is disabled', function (): void {
    $link = OneClickLogin::for('test@example.com')->generate();

    expect($link->url)->not()->toContain('signature=');
});

// security.require_https

it('generates https URLs and rejects plain HTTP when require_https is enabled', function (): void {
    config(['oneclicklogin.security.require_https' => true]);
    createTestUser('test@example.com');

    $link = OneClickLogin::for('test@example.com')->redirectTo('/dashboard')->generate();

    expect($link->url)->toStartWith('https://');

    $this->getJson('http://localhost/magic-link/verify/'.$link->token)
        ->assertStatus(403)
        ->assertJson(['success' => false]);
    $this->assertGuest();

    $this->get('https://localhost/magic-link/verify/'.$link->token)->assertRedirectContains('/dashboard');
    $this->assertAuthenticated();
});

// ip_binding / device_binding

it('rejects links bound to another IP address', function (): void {
    createTestUser('test@example.com');

    $link = OneClickLogin::for('test@example.com')->bindToIp('10.0.0.1')->generate();

    expect($link->ip_address)->toBe('10.0.0.1');

    $this->getJson('/magic-link/verify/'.$link->token)
        ->assertStatus(403)
        ->assertJson(['success' => false]);

    $this->assertGuest();
    expect($link->fresh()->isUsed())->toBeFalse();
});

it('accepts links bound to the requesting IP address', function (): void {
    createTestUser('test@example.com');

    $link = OneClickLogin::for('test@example.com')->bindToIp('127.0.0.1')->generate();

    expect($link->ip_address)->toBe('127.0.0.1');

    $this->getJson('/magic-link/verify/'.$link->token)->assertOk()->assertJson(['success' => true]);
    $this->assertAuthenticated();
});

it('enforces device binding on the user agent', function (): void {
    createTestUser('test@example.com');

    $link = OneClickLogin::for('test@example.com')->bindToDevice('Device-A')->generate();

    $this->withHeaders(['User-Agent' => 'Device-B'])
        ->getJson('/magic-link/verify/'.$link->token)
        ->assertStatus(403);
    $this->assertGuest();

    $this->withHeaders(['User-Agent' => 'Device-A'])
        ->getJson('/magic-link/verify/'.$link->token)
        ->assertOk();
    $this->assertAuthenticated();
});

it('does not bind links generated from the console', function (): void {
    config(['oneclicklogin.ip_binding' => true, 'oneclicklogin.device_binding' => true]);

    $link = OneClickLogin::for('test@example.com')->generate();

    expect($link->ip_address)->toBeNull()
        ->and($link->user_agent)->toBeNull();
});

it('refuses to consume a bound link for another client', function (): void {
    $link = OneClickLogin::for('test@example.com')->bindToIp('10.0.0.1')->generate();

    $consumer = OneClickLogin::consume($link->token);

    expect($consumer->allowsClient('10.0.0.2', null))->toBeFalse()
        ->and($consumer->consume('10.0.0.2'))->toBeNull()
        ->and($consumer->allowsClient('10.0.0.1', null))->toBeTrue()
        ->and($consumer->consume('10.0.0.1'))->toBeInstanceOf(MagicLink::class);
});
