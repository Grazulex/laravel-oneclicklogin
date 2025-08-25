<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Console;

use Grazulex\OneClickLogin\Models\MagicLink;
use Illuminate\Console\Command;

class ListMagicLinks extends Command
{
    protected $signature = 'oneclicklogin:list
                            {--email= : Filter by email}
                            {--status=all : Filter by status (all, active, expired, used)}
                            {--limit=50 : Number of links to show}';

    protected $description = 'List magic links';

    public function handle(): int
    {
        $email = $this->option('email');
        $status = $this->option('status');
        $limit = (int) $this->option('limit');

        $query = MagicLink::query()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($email) {
            $query->where('email', $email);
        }

        switch ($status) {
            case 'active':
                $query->active();
                break;
            case 'expired':
                $query->expired();
                break;
            case 'used':
                $query->where('used_at', '!=', null);
                break;
        }

        $links = $query->get();

        if ($links->isEmpty()) {
            $this->info('No magic links found.');

            return self::SUCCESS;
        }

        $headers = ['ID', 'Email', 'Status', 'Created', 'Expires', 'Used', 'URL'];
        $rows = [];

        foreach ($links as $link) {
            $status = $link->isExpired() ? 'Expired' :
                     ($link->used_at ? 'Used' : 'Active');

            $rows[] = [
                $link->ulid,
                $link->email,
                $status,
                $link->created_at->format('Y-m-d H:i'),
                $link->expires_at->format('Y-m-d H:i'),
                $link->used_at?->format('Y-m-d H:i') ?: '-',
                str_limit($link->redirect_url, 50),
            ];
        }

        $this->table($headers, $rows);

        return self::SUCCESS;
    }
}
