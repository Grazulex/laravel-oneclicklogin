<?php

declare(strict_types=1);

use Grazulex\OneClickLogin\Facades\OneClickLogin;
use Grazulex\OneClickLogin\Models\MagicLink;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

uses(RefreshDatabase::class);

function dropTokenLookupColumn(): void
{
    Schema::table('magic_links', function (Blueprint $table): void {
        $table->dropIndex(['token_lookup']);
    });

    Schema::table('magic_links', function (Blueprint $table): void {
        $table->dropColumn('token_lookup');
    });

    MagicLink::flushTokenLookupCache();
}

function createLegacyLink(string $token, string $email = 'legacy@example.com'): MagicLink
{
    return MagicLink::create([
        'email' => $email,
        'token_hash' => Hash::make($token),
        'redirect_url' => '/dashboard',
        'expires_at' => now()->addMinutes(15),
    ]);
}

it('has an indexed token_lookup column', function (): void {
    expect(Schema::hasColumn('magic_links', 'token_lookup'))->toBeTrue()
        ->and(MagicLink::supportsTokenLookup())->toBeTrue();

    $indexedColumns = collect(Schema::getIndexes('magic_links'))->pluck('columns')->flatten()->all();

    expect($indexedColumns)->toContain('token_lookup');
});

it('stores the SHA-256 lookup identifier for every hash algorithm', function (string $algorithm): void {
    config(['oneclicklogin.security.hash_algorithm' => $algorithm]);

    $link = OneClickLogin::for('test@example.com')->generate();

    expect($link->fresh()->token_lookup)->toBe(hash('sha256', $link->token))
        ->and(MagicLink::lookupFor($link->token))->toBe(hash('sha256', $link->token));
})->with(['sha256', 'bcrypt']);

it('finds bcrypt links with a single indexed query, without scanning', function (): void {
    config(['oneclicklogin.security.hash_algorithm' => 'bcrypt']);

    // Other active bcrypt links must not be verified
    OneClickLogin::for('first@example.com')->generate();
    OneClickLogin::for('second@example.com')->generate();
    $link = OneClickLogin::for('test@example.com')->generate();

    DB::enableQueryLog();
    $consumer = OneClickLogin::consume($link->token);
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    expect($consumer->isValid())->toBeTrue()
        ->and($consumer->getMagicLink()?->id)->toBe($link->id)
        ->and($queries->filter(fn (string $sql): bool => str_contains(mb_strtolower($sql), 'like'))->all())->toBeEmpty();
});

it('rejects an unknown token with a single query when legacy_token_scan is disabled', function (): void {
    config([
        'oneclicklogin.security.hash_algorithm' => 'bcrypt',
        'oneclicklogin.security.legacy_token_scan' => false,
    ]);

    OneClickLogin::for('test@example.com')->generate();
    MagicLink::supportsTokenLookup(); // warm the column detection cache

    DB::enableQueryLog();
    $link = MagicLink::findByToken('unknown-token');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($link)->toBeNull()
        ->and($queries)->toHaveCount(1);
});

it('rejects a link whose lookup identifier matches but whose hash does not', function (): void {
    config(['oneclicklogin.security.hash_algorithm' => 'bcrypt']);

    $link = OneClickLogin::for('test@example.com')->generate();
    $link->forceFill(['token_hash' => Hash::make('another-token')])->save();

    expect(MagicLink::findByToken($link->token))->toBeNull()
        ->and(OneClickLogin::consume($link->token)->isValid())->toBeFalse();
});

it('still finds links created before token_lookup existed', function (): void {
    $legacy = createLegacyLink('legacytoken123');

    expect($legacy->fresh()->token_lookup)->toBeNull()
        ->and(MagicLink::findByToken('legacytoken123')?->id)->toBe($legacy->id);
});

it('still finds SHA-256 links that have no token_lookup value', function (): void {
    $link = MagicLink::create([
        'email' => 'old@example.com',
        'token_hash' => hash('sha256', 'oldsha256token'),
        'redirect_url' => '/dashboard',
        'expires_at' => now()->addMinutes(15),
    ]);

    expect(MagicLink::findByToken('oldsha256token')?->id)->toBe($link->id);
});

it('skips legacy links when legacy_token_scan is disabled', function (): void {
    config(['oneclicklogin.security.hash_algorithm' => 'bcrypt']);

    createLegacyLink('legacytoken123');
    $link = OneClickLogin::for('test@example.com')->generate();

    config(['oneclicklogin.security.legacy_token_scan' => false]);

    expect(MagicLink::findByToken('legacytoken123'))->toBeNull()
        ->and(MagicLink::findByToken($link->token)?->id)->toBe($link->id);
});

it('keeps working when the token_lookup migration has not been run', function (): void {
    dropTokenLookupColumn();

    expect(MagicLink::supportsTokenLookup())->toBeFalse();

    $sha = OneClickLogin::for('sha@example.com')->generate();

    config(['oneclicklogin.security.hash_algorithm' => 'bcrypt']);
    $bcrypt = OneClickLogin::for('bcrypt@example.com')->generate();

    expect(MagicLink::findByToken($sha->token)?->id)->toBe($sha->id)
        ->and(MagicLink::findByToken($bcrypt->token)?->id)->toBe($bcrypt->id)
        ->and(MagicLink::findByToken('unknown-token'))->toBeNull();
});

it('adds the token_lookup column with the upgrade migration', function (): void {
    dropTokenLookupColumn();

    $migration = require __DIR__.'/../../database/migrations/add_token_lookup_to_magic_links_table.php';
    $migration->up();

    expect(Schema::hasColumn('magic_links', 'token_lookup'))->toBeTrue()
        ->and(MagicLink::supportsTokenLookup())->toBeTrue();

    // Running it again is a no-op
    $migration->up();

    expect(Schema::hasColumn('magic_links', 'token_lookup'))->toBeTrue();
});

it('publishes the upgrade migration', function (): void {
    $paths = ServiceProvider::pathsToPublish(null, 'oneclicklogin-upgrade-migrations');

    expect(array_keys($paths))->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('add_token_lookup_to_magic_links_table.php');

    $all = array_keys(ServiceProvider::pathsToPublish(null, 'oneclicklogin-migrations'));

    expect(collect($all)->map(fn (string $path): string => basename($path))->all())
        ->toContain('create_magic_links_table.php')
        ->toContain('add_token_lookup_to_magic_links_table.php');
});
