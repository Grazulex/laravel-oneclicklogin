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
 * @property string|null $token_lookup
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
        'token_lookup',
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
     * Detected presence of the token_lookup column, per connection and table.
     *
     * @var array<string, bool>
     */
    private static array $lookupColumnCache = [];

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
     * Compute the lookup identifier of a plain token (SHA-256), stored in the
     * indexed token_lookup column whatever the hash algorithm.
     */
    public static function lookupFor(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Whether the magic_links table has the token_lookup column
     * (installations upgraded without running the new migration do not).
     *
     * Only a positive detection is cached, so running the migration takes
     * effect without restarting long-lived processes.
     */
    public static function supportsTokenLookup(): bool
    {
        $model = static::query()->getModel();
        $key = ($model->getConnectionName() ?? 'default').'|'.$model->getTable();

        if (self::$lookupColumnCache[$key] ?? false) {
            return true;
        }

        $exists = $model->getConnection()
            ->getSchemaBuilder()
            ->hasColumn($model->getTable(), 'token_lookup');

        if ($exists) {
            self::$lookupColumnCache[$key] = true;
        }

        return $exists;
    }

    /**
     * Forget the cached token_lookup column detection (e.g. after migrating).
     */
    public static function flushTokenLookupCache(): void
    {
        self::$lookupColumnCache = [];
    }

    /**
     * Find a magic link matching the given plain token.
     *
     * The link is found with a single indexed query on token_lookup (or on
     * token_hash for SHA-256 links), then its hash is verified once. Only links
     * created before token_lookup existed, hashed with bcrypt/argon2id, are
     * verified one by one (see security.legacy_token_scan).
     *
     * @param  Builder<MagicLink>|null  $query
     */
    public static function findByToken(string $token, ?Builder $query = null): ?self
    {
        $query ??= static::query();
        $lookup = static::lookupFor($token);
        $hasLookupColumn = static::supportsTokenLookup();

        $link = (clone $query)
            ->where(function (Builder $where) use ($lookup, $hasLookupColumn): void {
                $where->where('token_hash', $lookup);

                if ($hasLookupColumn) {
                    $where->orWhere('token_lookup', $lookup);
                }
            })
            ->first();

        if ($link instanceof self) {
            return $link->verifyToken($token) ? $link : null;
        }

        if (! filter_var(config('oneclicklogin.security.legacy_token_scan', true), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $legacy = (clone $query)->where('token_hash', 'like', '$%');

        if ($hasLookupColumn) {
            $legacy->whereNull('token_lookup');
        }

        foreach ($legacy->lazyById(100) as $candidate) {
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

        if (static::supportsTokenLookup()) {
            $this->token_lookup = static::lookupFor($this->token);
        }
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
