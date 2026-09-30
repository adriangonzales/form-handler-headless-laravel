<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FormEntryExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FormEntryExport
 */
class FormEntryExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'form_id' => $this->form_id,
            'status' => $this->status,
            'parameters' => (object) $this->parameters,
            'filename' => $this->filename,
            'row_count' => $this->row_count,
            'error' => $this->error,
            'download_url' => $this->isCompleted() ? route('entry-exports.download', $this->resource) : null,
            'completed_at' => $this->completed_at,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
