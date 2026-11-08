<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Support;

use Illuminate\Support\Facades\Route;

/**
 * Resolves where users are sent when a magic link cannot be used.
 */
class InvalidLinkRedirect
{
    /**
     * Get the redirect target for invalid magic links.
     *
     * Reads `redirect_on_invalid`. The legacy `login_redirect_url` key, when set,
     * still takes precedence for backward compatibility.
     */
    public static function url(): string
    {
        foreach (['oneclicklogin.login_redirect_url', 'oneclicklogin.redirect_on_invalid'] as $key) {
            $value = config($key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return Route::has('login') ? route('login') : '/login';
    }

    /**
     * Determine whether a redirect location points to the invalid link target
     * (or to a login page), meaning the magic link was not accepted.
     */
    public static function isFailureLocation(?string $location): bool
    {
        if ($location === null || $location === '') {
            return false;
        }

        if (str_contains($location, '/login')) {
            return true;
        }

        $targetPath = parse_url(url(self::url()), PHP_URL_PATH);
        $locationPath = parse_url(url($location), PHP_URL_PATH);

        return is_string($targetPath) && $targetPath === $locationPath;
    }
}
