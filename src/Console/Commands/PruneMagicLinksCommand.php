<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Console\Commands;

use Grazulex\OneClickLogin\Services\MagicLinkManager;
use Illuminate\Console\Command;

class PruneMagicLinksCommand extends Command
{
    protected $signature = 'oneclicklogin:prune
                            {--force : Skip confirmation}';

    protected $description = 'Remove expired magic links from the database';

    public function handle(MagicLinkManager $manager): int
    {
        if (! $this->option('force') && ! $this->confirm('Are you sure you want to delete all expired magic links?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $deletedCount = $manager->prune();

        if ($deletedCount === 0) {
            $this->info('No expired magic links found.');
        } else {
            $this->info("Successfully deleted {$deletedCount} expired magic links.");
        }

        return self::SUCCESS;
    }
}
