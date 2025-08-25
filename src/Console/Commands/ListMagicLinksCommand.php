<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Console\Commands;

use Grazulex\OneClickLogin\Models\MagicLink;
use Illuminate\Console\Command;

class ListMagicLinksCommand extends Command
{
    protected $signature = 'oneclicklogin:list
                            {--email= : Filter by email}
                            {--active : Show only active links}
                            {--expired : Show only expired links}
                            {--used : Show only used links}';

    protected $description = 'List magic links';

    public function handle(): int
    {
        $query = MagicLink::query();

        if ($email = $this->option('email')) {
            $query->where('email', $email);
        }

        if ($this->option('active')) {
            $query->active();
        } elseif ($this->option('expired')) {
            $query->expired();
        } elseif ($this->option('used')) {
            $query->used();
        }

        $links = $query->orderBy('created_at', 'desc')->get();

        if ($links->isEmpty()) {
            $this->info('No magic links found.');

            return self::SUCCESS;
        }

        $headers = ['ID', 'ULID', 'Email', 'Status', 'Expires At', 'Created At'];
        $rows = [];

        foreach ($links as $link) {
            $status = $link->isUsed() ? 'Used' : ($link->isExpired() ? 'Expired' : 'Active');

            $rows[] = [
                $link->id,
                mb_substr($link->ulid, 0, 12).'...',
                $link->email,
                $status,
                $link->expires_at->format('Y-m-d H:i:s'),
                $link->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $this->table($headers, $rows);
        $this->info("Total: {$links->count()} magic links");

        return self::SUCCESS;
    }
}
