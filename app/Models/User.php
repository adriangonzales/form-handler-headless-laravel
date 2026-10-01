<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property int $token_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'token_version'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'token_version' => 'integer',
        ];
    }

    /**
     * @return HasMany<Form, $this>
     */
    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    /**
     * Entry exports of the user's forms, leaving out forms that have been deleted.
     *
     * @return HasManyThrough<FormEntryExport, Form, $this>
     */
    public function entryExports(): HasManyThrough
    {
        return $this->hasManyThrough(FormEntryExport::class, Form::class);
    }

    /**
     * Revoke every token issued so far by bumping the token version carried in each token's "tv"
     * claim. Tokens issued afterwards carry the new version and remain valid.
     */
    public function revokeTokens(): void
    {
        $this->forceFill(['token_version' => $this->token_version + 1])->save();
    }

    /**
     * Determine whether a token carrying the given version was revoked by revokeTokens(). Tokens
     * issued before versions existed have no claim and count as version 0.
     */
    public function tokenIsRevoked(?int $tokenVersion): bool
    {
        return ($tokenVersion ?? 0) !== $this->token_version;
    }

    /**
     * Get the identifier stored in the JWT's subject claim.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Get custom claims to add to the JWT.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return ['tv' => $this->token_version];
    }
}
