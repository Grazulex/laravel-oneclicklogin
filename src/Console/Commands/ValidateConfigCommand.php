<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Console\Commands;

use DB;
use Exception;
use Grazulex\OneClickLogin\Support\ConfigValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Schema;

class ValidateConfigCommand extends Command
{
    protected $signature = 'oneclicklogin:validate';

    protected $description = 'Validate OneClickLogin configuration';

    public function handle(): int
    {
        $this->info('Validating OneClickLogin configuration...');

        try {
            $this->validateUserModel();
            $this->validateEmailField();
            $this->validateExpirationTime();
            $this->validateTokenLength();
            $this->validateRedirectUrls();
            $this->validateDatabase();

            $this->info('✅ Configuration validation passed!');

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Configuration validation failed:');
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    protected function validateUserModel(): void
    {
        $model = Config::get('oneclicklogin.user_model');
        $this->line("Validating user model: {$model}");

        ConfigValidator::validateUserModel($model);
        $this->line('✓ User model is valid');
    }

    protected function validateEmailField(): void
    {
        $model = Config::get('oneclicklogin.user_model');
        $field = Config::get('oneclicklogin.email_field');

        $this->line("Validating email field: {$field}");

        ConfigValidator::validateEmailField($model, $field);
        $this->line('✓ Email field is valid');
    }

    protected function validateExpirationTime(): void
    {
        $minutes = Config::get('oneclicklogin.ttl_minutes');

        $this->line("Validating expiration time: {$minutes} minutes");

        ConfigValidator::validateExpirationTime($minutes);
        $this->line('✓ Expiration time is valid');
    }

    protected function validateTokenLength(): void
    {
        $length = Config::get('oneclicklogin.token_length');

        $this->line("Validating token length: {$length} characters");

        ConfigValidator::validateTokenLength($length);
        $this->line('✓ Token length is valid');
    }

    protected function validateRedirectUrls(): void
    {
        $defaultUrl = Config::get('oneclicklogin.default_redirect_url');
        $loginUrl = Config::get('oneclicklogin.login_redirect_url');

        $this->line('Validating redirect URLs...');

        ConfigValidator::validateRedirectUrl($defaultUrl);
        ConfigValidator::validateRedirectUrl($loginUrl);

        $this->line('✓ Redirect URLs are valid');
    }

    protected function validateDatabase(): void
    {
        $this->line('Validating database connection...');

        try {
            DB::connection()->getPdo();
            $this->line('✓ Database connection is working');
        } catch (Exception $e) {
            throw new Exception("Database connection failed: {$e->getMessage()}", $e->getCode(), $e);
        }

        // Check if migration has been run
        if (! Schema::hasTable('magic_links')) {
            throw new Exception('Magic links table not found. Run: php artisan migrate');
        }

        $this->line('✓ Magic links table exists');
    }
}
