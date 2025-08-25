<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Console;

use Grazulex\OneClickLogin\Models\MagicLink;
use Illuminate\Console\Command;

class RevokeMagicLink extends Command
{
    protected $signature = 'oneclicklogin:revoke
                            {id : The ULID of the magic link to revoke}';

    protected $description = 'Revoke a specific magic link';

    public function handle(): int
    {
        $id = $this->argument('id');

        $link = MagicLink::where('ulid', $id)->first();

        if (! $link) {
            $this->error("Magic link with ID '{$id}' not found.");

            return self::FAILURE;
        }

        if ($link->used_at) {
            $this->warn("Magic link '{$id}' is already used.");

            return self::SUCCESS;
        }

        if ($link->isExpired()) {
            $this->warn("Magic link '{$id}' is already expired.");

            return self::SUCCESS;
        }

        $link->revoke();

        $this->info("Magic link '{$id}' has been revoked.");

        return self::SUCCESS;
    }
}
