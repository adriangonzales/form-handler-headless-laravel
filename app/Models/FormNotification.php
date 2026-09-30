<?php

namespace App\Models;

use Database\Factories\FormNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'form_id',
    'type',
    'value',
    'enabled',
    'error',
])]
class FormNotification extends Model
{
    /** @use HasFactory<FormNotificationFactory> */
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
            'enabled' => 'boolean',
        ];
    }

    /**
     * The model's default values for attributes. Matches the database default: a new recipient is
     * alerted unless it is added disabled.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'enabled' => true,
    ];

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
