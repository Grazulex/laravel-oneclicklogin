<?php

declare(strict_types=1);

namespace Grazulex\OneClickLogin\Models;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        $this->token_hash = Hash::make($this->token);
    }

    /**
     * Verify a token against the stored hash
     */
    public function verifyToken(string $token): bool
    {
        return Hash::check($token, $this->token_hash);
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

        if ($ipAddress) {
            $this->ip_address = $ipAddress;
        }

        if ($userAgent) {
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
        $routeName = config('oneclicklogin.route_name', 'magic-link.verify');

        if (! isset($this->token)) {
            // If token is not set, we can't generate the URL
            return '#';
        }

        try {
            return route($routeName, ['token' => $this->token]);
        } catch (Exception $e) {
            // Fallback if route doesn't exist
            return url('/magic-link/verify/'.$this->token);
        }
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
}
