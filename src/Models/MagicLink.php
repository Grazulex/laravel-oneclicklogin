<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Models;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string $ulid
 * @property string $email
 * @property string $token_hash
 * @property string $redirect_url
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property array|null $context
 * @property array|null $meta
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MagicLink extends Model
{
    use HasUlids;

    /**
     * The token attribute (not stored, generated each time)
     */
    public string $token;

    protected $table = 'magic_links';

    protected $fillable = [
        'email',
        'token_hash',
        'redirect_url',
        'expires_at',
        'used_at',
        'context',
        'meta',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'context' => 'array',
        'meta' => 'array',
    ];

    /**
     * Hash a token using the configured algorithm (security.hash_algorithm).
     *
     * Supported algorithms: sha256 (default), bcrypt, argon2id.
     */
    public static function hashToken(string $token, ?string $algorithm = null): string
    {
        $algorithm = mb_strtolower((string) ($algorithm ?? config('oneclicklogin.security.hash_algorithm', 'sha256')));

        return match ($algorithm) {
            'sha256' => hash('sha256', $token),
            'bcrypt' => Hash::driver('bcrypt')->make($token),
            'argon2id' => Hash::driver('argon2id')->make($token),
            default => throw new InvalidArgumentException("Unsupported magic link hash algorithm [{$algorithm}]. Supported: sha256, bcrypt, argon2id."),
        };
    }

    /**
     * Find a magic link matching the given plain token.
     *
     * SHA-256 hashes are looked up directly; bcrypt/argon2id hashes (including
     * links created by previous versions) are verified one by one.
     *
     * @param  Builder<MagicLink>|null  $query
     */
    public static function findByToken(string $token, ?Builder $query = null): ?self
    {
        $query ??= static::query();

        $link = (clone $query)->where('token_hash', hash('sha256', $token))->first();

        if ($link instanceof self) {
            return $link;
        }

        $candidates = (clone $query)->where('token_hash', 'like', '$%')->get();

        foreach ($candidates as $candidate) {
            if ($candidate->verifyToken($token)) {
                return $candidate;
            }
        }

        return null;
    }

    // Override ULID column to not be the primary key
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * Generate a new token and hash it
     */
    public function generateToken(): void
    {
        $this->token = Str::random(64);
        $this->token_hash = static::hashToken($this->token);
    }

    /**
     * Verify a token against the stored hash
     */
    public function verifyToken(string $token): bool
    {
        $hash = (string) $this->token_hash;

        if ($hash === '') {
            return false;
        }

        // bcrypt / argon2 hashes (also used by links created before hash_algorithm was supported)
        if (str_starts_with($hash, '$')) {
            return password_verify($token, $hash);
        }

        return hash_equals($hash, hash('sha256', $token));
    }

    /**
     * Determine whether the given client is allowed to use this link.
     *
     * A link bound to an IP address (ip_binding) or a device (device_binding)
     * stores the binding in ip_address / user_agent until it is used.
     */
    public function allowsClient(?string $ipAddress, ?string $userAgent): bool
    {
        if ($this->isUsed()) {
            return true;
        }

        if (! in_array($this->ip_address, [null, ''], true) && $this->ip_address !== $ipAddress) {
            return false;
        }

        return in_array($this->user_agent, [null, ''], true) || $this->user_agent === $userAgent;
    }

    /**
     * Check if the magic link is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the magic link has been used
     */
    public function isUsed(): bool
    {
        return ! is_null($this->used_at);
    }

    /**
     * Check if the magic link is valid (not expired and not used)
     */
    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isUsed();
    }

    /**
     * Mark the magic link as used
     */
    public function markAsUsed(?string $ipAddress = null, ?string $userAgent = null): void
    {
        $this->used_at = now();

        if (! in_array($ipAddress, [null, '', '0'], true)) {
            $this->ip_address = $ipAddress;
        }

        if (! in_array($userAgent, [null, '', '0'], true)) {
            $this->user_agent = $userAgent;
        }

        $this->save();
    }

    /**
     * Revoke the magic link by marking it as used
     */
    public function revoke(): void
    {
        $this->markAsUsed();
    }

    /**
     * Get the full URL for this magic link
     */
    public function getUrlAttribute(): string
    {
        if (! isset($this->token)) {
            // If token is not set, we can't generate the URL
            return '#';
        }

        $routeName = $this->resolveRouteName();
        $url = null;

        if ($routeName !== null) {
            try {
                $parameters = ['token' => $this->token];

                if (filter_var(config('oneclicklogin.security.signed_urls', false), FILTER_VALIDATE_BOOLEAN)) {
                    // Relative signature: independent of host/scheme (proxies, require_https)
                    $signed = URL::temporarySignedRoute($routeName, $this->expires_at, $parameters, false);
                    parse_str((string) parse_url($signed, PHP_URL_QUERY), $query);
                    $parameters = array_merge($parameters, $query);
                }

                $url = route($routeName, $parameters);
            } catch (Exception) {
                $url = null;
            }
        }

        // Fallback if route doesn't exist
        $url ??= url('/magic-link/verify/'.$this->token);

        if (filter_var(config('oneclicklogin.security.require_https', false), FILTER_VALIDATE_BOOLEAN)) {
            $url = preg_replace('#^http://#i', 'https://', $url) ?? $url;
        }

        return $url;
    }

    /**
     * Scope: Active magic links (not expired, not used)
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now())
            ->whereNull('used_at');
    }

    /**
     * Scope: Expired magic links
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope: Used magic links
     */
    public function scopeUsed($query)
    {
        return $query->whereNotNull('used_at');
    }

    /**
     * Resolve the route used to build magic link URLs.
     *
     * Reads `signed_route_name`; the legacy `route_name` key still takes precedence
     * when set. Falls back to the package route when the configured one does not exist.
     */
    protected function resolveRouteName(): ?string
    {
        $candidates = [
            config('oneclicklogin.route_name'),
            config('oneclicklogin.signed_route_name'),
            'magic-link.verify',
        ];

        foreach ($candidates as $name) {
            if (is_string($name) && $name !== '' && Route::has($name)) {
                return $name;
            }
        }

        return null;
    }
}
