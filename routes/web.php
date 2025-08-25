<?php

declare(strict_types=1);

use Grazulex\OneClickLogin\Http\Controllers\MagicLinkController;
use Grazulex\OneClickLogin\Http\Middleware\LogMagicLinkAttempts;
use Grazulex\OneClickLogin\Http\Middleware\RateLimitMagicLinks;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])
    ->prefix('magic-link')
    ->name('magic-link.')
    ->group(function () {
        Route::get('/verify/{token}', [MagicLinkController::class, 'verify'])
            ->name('verify')
            ->where('token', '[a-zA-Z0-9]+')
            ->middleware([
                RateLimitMagicLinks::class.':10,5', // 10 attempts per 5 minutes
                LogMagicLinkAttempts::class,
            ]);
    });
