<?php

namespace App\Models;

use App\Data\FormSettings;
use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property list<array{id: string, order: int, label?: string, name?: string, rules?: list<string>|string}>|null $schema
 * @property FormSettings|null $settings
 */
#[Fillable([
    'user_id',
    'name',
    'active',
    'schema',
    'settings',
])]
class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory;

    use HasUlids;
    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'schema' => 'array',
            'settings' => FormSettings::class,
        ];
    }

    /**
     * Get the schema's fields sorted by their `order`, for display.
     *
     * @return array<int, array{id: string, order: int, label?: string, name?: string, rules?: list<string>|string}>
     */
    public function orderedSchema(): array
    {
        return collect($this->schema ?? [])
            ->sortBy('order')
            ->values()
            ->all();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<FormEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(FormEntry::class);
    }

    /**
     * @return HasMany<FormEntryExport, $this>
     */
    public function entryExports(): HasMany
    {
        return $this->hasMany(FormEntryExport::class);
    }

    /**
     * @return HasMany<FormNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(FormNotification::class);
    }
}
