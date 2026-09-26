<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[\Illuminate\Database\Eloquent\Attributes\Fillable([
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
            'spam_score' => 'decimal',
            'starred' => 'boolean',
            'read_at' => 'timestamp',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
