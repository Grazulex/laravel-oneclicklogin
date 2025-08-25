<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('has magic_links table with correct columns', function (): void {
    expect(Schema::hasTable('magic_links'))->toBeTrue();

    expect(Schema::hasColumn('magic_links', 'id'))->toBeTrue();
    expect(Schema::hasColumn('magic_links', 'ulid'))->toBeTrue();
    expect(Schema::hasColumn('magic_links', 'email'))->toBeTrue();
    expect(Schema::hasColumn('magic_links', 'token_hash'))->toBeTrue();
    expect(Schema::hasColumn('magic_links', 'redirect_url'))->toBeTrue();
    expect(Schema::hasColumn('magic_links', 'expires_at'))->toBeTrue();
    expect(Schema::hasColumn('magic_links', 'used_at'))->toBeTrue();
    expect(Schema::hasColumn('magic_links', 'context'))->toBeTrue();
    expect(Schema::hasColumn('magic_links', 'meta'))->toBeTrue();
});
