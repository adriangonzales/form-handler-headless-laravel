<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FormEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<string, mixed>|null $input
 * @property array{platform: string|null, browser: string|null, browser_version: string|null}|null $user_agent_display
 * @property CarbonImmutable|null $spam_checked_at
 * @property CarbonImmutable|null $read_at
 */
#[Fillable([
    'form_id',
    'input',
    'ip',
    'ip_location_display',
    'referer',
    'user_agent',
    'user_agent_display',
    'spam',
    'spam_score',
    'spam_reason',
    'spam_checked_at',
    'starred',
    'read_at',
])]
class FormEntry extends Model
{
    /** @use HasFactory<FormEntryFactory> */
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
            'input' => 'array',
            'user_agent_display' => 'array',
            'spam' => 'boolean',
            'spam_score' => 'decimal:3',
            'spam_checked_at' => 'datetime',
            'starred' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'spam' => false,
        'spam_score' => 0,
        'starred' => 0,
    ];

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
