<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Console;

use Grazulex\OneClickLogin\Services\MagicLinkManager;
use Illuminate\Console\Command;

class PruneMagicLinks extends Command
{
    protected $signature = 'oneclicklogin:prune {--days=7 : Number of days to keep expired links}';

    protected $description = 'Prune expired and used magic links';

    public function handle(MagicLinkManager $manager): int
    {
        $days = (int) $this->option('days');

        $this->info("Pruning magic links older than {$days} days...");

        $pruned = $manager->prune($days);

        $this->info("Pruned {$pruned} magic links.");

        return self::SUCCESS;
    }
}
