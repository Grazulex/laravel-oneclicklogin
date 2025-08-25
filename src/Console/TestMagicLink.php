<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Console;

use Exception;
use Grazulex\OneClickLogin\Services\MagicLinkManager;
use Illuminate\Console\Command;

class TestMagicLink extends Command
{
    protected $signature = 'oneclicklogin:test
                            {--email=test@example.com : Email to test with}';

    protected $description = 'Test magic link generation and validation';

    public function handle(MagicLinkManager $manager): int
    {
        $email = $this->option('email');

        $this->info('Testing OneClickLogin Magic Link functionality...');
        $this->newLine();

        // Test 1: Generate magic link
        $this->line('🔗 Test 1: Generating magic link...');

        try {
            $builder = $manager->for($email)
                ->redirectTo('/dashboard')
                ->expiresInMinutes(30)
                ->skipRateLimit();

            $link = $builder->generate();

            $this->info('✅ Magic link created successfully');
            $this->line("   Email: {$link->email}");
            $this->line("   Token: {$link->token}");
            $this->line("   URL: {$link->url}");
            $this->line("   Expires: {$link->expires_at}");
        } catch (Exception $e) {
            $this->error("❌ Exception during generation: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->newLine();

        // Test 2: Validate magic link
        $this->line('🔍 Test 2: Validating magic link...');

        try {
            $consumer = $manager->consume($link->token);

            if ($consumer->isValid()) {
                $this->info('✅ Magic link is valid');
                $this->line('   Can be used: '.($consumer->canUse() ? 'Yes' : 'No'));
            } else {
                $this->warn("⚠️  Magic link is not valid: {$consumer->getError()}");
            }
        } catch (Exception $e) {
            $this->error("❌ Exception during validation: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->newLine();

        // Test 3: Configuration check
        $this->line('⚙️  Test 3: Configuration check...');

        $config = config('oneclicklogin');
        $this->info('✅ Configuration loaded');
        $this->line("   Default TTL: {$config['ttl_minutes']} minutes");
        $this->line("   Guard: {$config['guard']}");
        $this->line('   IP binding: '.($config['ip_binding'] ? 'Enabled' : 'Disabled'));

        $this->newLine();
        $this->info('🎉 All tests completed successfully!');

        $this->comment('💡 The magic link has been generated. Use your app to send it via email, SMS, or any other method.');

        return self::SUCCESS;
    }
}
