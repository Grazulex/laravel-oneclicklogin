<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin;

use Grazulex\OneClickLogin\Console\Commands\GenerateMagicLinkCommand;
use Grazulex\OneClickLogin\Console\Commands\ListMagicLinksCommand;
use Grazulex\OneClickLogin\Console\Commands\PruneMagicLinksCommand;
use Grazulex\OneClickLogin\Console\Commands\RevokeMagicLinkCommand;
use Grazulex\OneClickLogin\Console\Commands\TestMagicLinkCommand;
use Grazulex\OneClickLogin\Console\Commands\ValidateConfigCommand;
use Grazulex\OneClickLogin\Services\MagicLinkManager;
use Illuminate\Support\ServiceProvider;

class OneClickLoginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/oneclicklogin.php', 'oneclicklogin');

        $this->app->singleton(MagicLinkManager::class, fn (): MagicLinkManager => new MagicLinkManager());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/oneclicklogin.php' => config_path('oneclicklogin.php'),
        ], 'oneclicklogin-config');

        if (! class_exists('CreateMagicLinksTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_magic_links_table.php' => database_path('migrations/'.date('Y_m_d_His').'_create_magic_links_table.php'),
            ], 'oneclicklogin-migrations');
        }

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateMagicLinkCommand::class,
                ListMagicLinksCommand::class,
                PruneMagicLinksCommand::class,
                RevokeMagicLinkCommand::class,
                TestMagicLinkCommand::class,
                ValidateConfigCommand::class,
            ]);
        }
    }
}
