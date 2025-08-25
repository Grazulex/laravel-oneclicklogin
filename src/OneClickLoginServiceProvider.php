<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin;

use Illuminate\Support\ServiceProvider;

class OneClickLoginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/oneclicklogin.php', 'oneclicklogin');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/oneclicklogin.php' => config_path('oneclicklogin.php'),
        ], 'oneclicklogin-config');
    }
}
