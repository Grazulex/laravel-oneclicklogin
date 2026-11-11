<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the indexed token_lookup column to installations created before it existed.
 * It is a no-op when the table already has the column (fresh installations)
 * or when the table does not exist yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('magic_links') || Schema::hasColumn('magic_links', 'token_lookup')) {
            return;
        }

        Schema::table('magic_links', function (Blueprint $table) {
            $table->string('token_lookup', 64)->nullable()->after('token_hash')->index();
        });
    }

    public function down(): void
    {
        // The column belongs to the create migration on fresh installations;
        // it is removed together with the table when that migration is rolled back.
    }
};
