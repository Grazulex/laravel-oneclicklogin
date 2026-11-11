<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Console\Commands;

use Exception;
use Grazulex\OneClickLogin\Services\MagicLinkManager;
use Illuminate\Console\Command;

class GenerateMagicLinkCommand extends Command
{
    protected $signature = 'oneclicklogin:generate
                            {email : The email to generate the magic link for}
                            {--url=/ : The URL to redirect to after login}
                            {--ttl= : Time to live in minutes}
                            {--force : Skip rate limiting}
                            {--send : Email the link to the address (notifications.mail class)}';

    protected $description = 'Generate a magic link for the specified email';

    public function handle(MagicLinkManager $manager): int
    {
        $email = $this->argument('email');
        $url = $this->option('url');
        $ttl = $this->option('ttl');
        $force = $this->option('force');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');

            return self::FAILURE;
        }

        try {
            $builder = $manager->for($email)->redirectTo($url);

            if ($ttl) {
                $builder->expiresInMinutes((int) $ttl);
            }

            if ($force) {
                $builder->skipRateLimit();
            }

            if ($this->option('send')) {
                $link = $builder->via('mail')->send();

                $this->info("Magic link generated for {$email}");
                $this->line("Link: {$link->url}");
                $this->info("Magic link sent by email to {$email}");

                return self::SUCCESS;
            }

            $link = $builder->generate();

            $this->info("Magic link generated for {$email}");
            $this->line("Link: {$link->url}");
            $this->comment('💡 The link has been generated. Use --send to email it, or send it via your preferred method (SMS, etc.)');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
