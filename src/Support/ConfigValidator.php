<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Support;

use InvalidArgumentException;

class ConfigValidator
{
    public static function validateUserModel(string $model): void
    {
        if (! class_exists($model)) {
            throw new InvalidArgumentException("User model class [{$model}] does not exist.");
        }

        $interfaces = class_implements($model);

        if (! isset($interfaces['Illuminate\Contracts\Auth\Authenticatable'])) {
            throw new InvalidArgumentException("User model [{$model}] must implement Authenticatable interface.");
        }
    }

    public static function validateEmailField(string $model, string $field): void
    {
        if (! class_exists($model)) {
            return; // Model validation will catch this
        }

        $instance = new $model();

        if (! isset($instance->getFillable()[$field]) &&
            ! in_array($field, $instance->getFillable()) &&
            ! in_array('*', $instance->getFillable())) {
            throw new InvalidArgumentException("Email field [{$field}] is not fillable in model [{$model}].");
        }
    }

    public static function validateExpirationTime(int $minutes): void
    {
        if ($minutes < 1) {
            throw new InvalidArgumentException('Magic link expiration time must be at least 1 minute.');
        }

        if ($minutes > 10080) { // 7 days
            throw new InvalidArgumentException('Magic link expiration time cannot exceed 7 days (10080 minutes).');
        }
    }

    public static function validateTokenLength(int $length): void
    {
        if ($length < 32) {
            throw new InvalidArgumentException('Token length must be at least 32 characters for security.');
        }

        if ($length > 255) {
            throw new InvalidArgumentException('Token length cannot exceed 255 characters.');
        }
    }

    public static function validateRedirectUrl(?string $url): void
    {
        if ($url === null) {
            return;
        }

        // Reject protocol-relative URLs like //evil.com
        if (str_starts_with($url, '//')) {
            throw new InvalidArgumentException("Protocol-relative URLs are not allowed: [{$url}]");
        }

        // Allow relative URLs
        if (str_starts_with($url, '/')) {
            return;
        }

        // Validate absolute URLs
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid redirect URL: [{$url}]");
        }

        // Check for allowed schemes
        $parsed = parse_url($url);
        if (! in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            throw new InvalidArgumentException("Redirect URL must use http or https scheme: [{$url}]");
        }
    }
}
