<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Console\Commands;

use Exception;
use Grazulex\OneClickLogin\Services\MagicLinkManager;
use Illuminate\Console\Command;

class RevokeMagicLinkCommand extends Command
{
    protected $signature = 'oneclicklogin:revoke
                            {token : The magic link token to revoke}
                            {--force : Skip confirmation}';

    protected $description = 'Revoke a magic link by marking it as used';

    public function handle(MagicLinkManager $manager): int
    {
        $token = $this->argument('token');

        if (! $this->option('force') && ! $this->confirm("Are you sure you want to revoke the magic link with token: {$token}?")) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            $manager->revoke($token);
            $this->info("Magic link with token {$token} has been revoked.");

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
