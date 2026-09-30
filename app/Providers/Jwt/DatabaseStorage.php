<?php

declare(strict_types=1);

namespace App\Providers\Jwt;

use App\Models\DeniedToken;
use Tymon\JWTAuth\Contracts\Providers\Storage;

/**
 * Stores the JWT blacklist in the `denied_tokens` table instead of the cache, so clearing the cache
 * cannot make logged-out or refreshed tokens valid again.
 */
class DatabaseStorage implements Storage
{
    /**
     * Add a token to the deny list until it can no longer be used.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $minutes
     */
    public function add($key, $value, $minutes): void
    {
        DeniedToken::query()->updateOrCreate(['jti' => $key], [
            'value' => $value,
            'expires_at' => now()->addMinutes($minutes),
        ]);
    }

    /**
     * Add a token to the deny list permanently.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function forever($key, $value): void
    {
        DeniedToken::query()->updateOrCreate(['jti' => $key], [
            'value' => $value,
            'expires_at' => null,
        ]);
    }

    /**
     * Get the stored value for a denied token, or null if it is not denied (or has expired).
     *
     * @param  string  $key
     */
    public function get($key): mixed
    {
        return DeniedToken::query()->active()->find($key)?->value;
    }

    /**
     * Remove a token from the deny list.
     *
     * @param  string  $key
     */
    public function destroy($key): bool
    {
        return DeniedToken::query()->whereKey($key)->delete() > 0;
    }

    /**
     * Empty the deny list.
     */
    public function flush(): void
    {
        DeniedToken::query()->delete();
    }
}
