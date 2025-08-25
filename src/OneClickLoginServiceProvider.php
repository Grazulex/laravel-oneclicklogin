<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin;

use Grazulex\OneClickLogin\Console\GenerateMagicLink;
use Grazulex\OneClickLogin\Console\ListMagicLinks;
use Grazulex\OneClickLogin\Console\PruneMagicLinks;
use Grazulex\OneClickLogin\Console\RevokeMagicLink;
use Grazulex\OneClickLogin\Console\TestMagicLink;
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateMagicLink::class,
                ListMagicLinks::class,
                PruneMagicLinks::class,
                RevokeMagicLink::class,
                TestMagicLink::class,
            ]);
        }
    }
}
