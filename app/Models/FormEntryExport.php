<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FormEntryExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A CSV export of a form's entries, generated in the background and kept until it expires.
 *
 * @property array{sort?: string, filter?: array<string, string>} $parameters
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $expires_at
 */
#[Fillable([
    'form_id',
    'status',
    'parameters',
    'disk',
    'path',
    'filename',
    'row_count',
    'error',
    'completed_at',
    'expires_at',
])]
class FormEntryExport extends Model
{
    /** @use HasFactory<FormEntryExportFactory> */
    use HasFactory;

    use HasUlids;
    use Prunable;

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_FAILED = 'failed';

    /**
     * How long an export, and its file, is kept after it is requested.
     */
    public const int RETENTION_HOURS = 24;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'row_count' => 'integer',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Get the expired exports to prune.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }

    /**
     * Delete the export's file before the record is pruned.
     */
    protected function pruning(): void
    {
        if ($this->path !== null) {
            Storage::disk($this->disk)->delete($this->path);
        }
    }
}
