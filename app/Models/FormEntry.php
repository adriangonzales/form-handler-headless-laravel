<?php

namespace App\Models;

use Database\Factories\FormEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<string, mixed>|null $data
 */
#[Fillable([
    'form_id',
    'data',
    'ip',
    'ip_location_display',
    'referer',
    'user_agent',
    'user_agent_display',
    'spam',
    'spam_score',
    'spam_reason',
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
            'data' => 'array',
            'spam' => 'boolean',
            'spam_score' => 'decimal:2',
            'starred' => 'boolean',
            'read_at' => 'timestamp',
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
