<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Console\Commands;

use Exception;
use Grazulex\OneClickLogin\Services\MagicLinkManager;
use Illuminate\Console\Command;

class TestMagicLinkCommand extends Command
{
    protected $signature = 'oneclicklogin:test
                            {--email=test@example.com : The email to test with}
                            {--ttl=60 : Time to live in minutes}';

    protected $description = 'Test magic link generation and validation';

    public function handle(MagicLinkManager $manager): int
    {
        $email = $this->option('email');
        $ttl = (int) $this->option('ttl');

        $this->info("Testing magic link functionality for: {$email}");

        try {
            // Generate a test magic link
            $this->line('1. Generating magic link...');
            $link = $manager->for($email)
                ->redirectTo('/dashboard')
                ->expiresInMinutes($ttl)
                ->generate();

            $this->info("✓ Magic link generated: {$link->token}");

            // Test validation
            $this->line('2. Testing validation...');
            $consumer = $manager->consume($link->token);

            if ($consumer->isValid()) {
                $this->info('✓ Magic link is valid');
            } else {
                $this->error('✗ Magic link is invalid');

                return self::FAILURE;
            }

            // Test consumption
            $this->line('3. Testing consumption...');
            $result = $consumer->consume();

            if ($result instanceof \Grazulex\OneClickLogin\Models\MagicLink) {
                $this->info('✓ Magic link consumed successfully');
            } else {
                $this->error('✗ Failed to consume magic link');

                return self::FAILURE;
            }

            // Test re-use (should fail)
            $this->line('4. Testing re-use prevention...');
            $consumer2 = $manager->consume($link->token);

            if (! $consumer2->isValid()) {
                $this->info('✓ Used magic link correctly rejected');
            } else {
                $this->error('✗ Used magic link was incorrectly accepted');

                return self::FAILURE;
            }

            $this->info('🎉 All tests passed! Magic link functionality is working correctly.');

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error("Test failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
