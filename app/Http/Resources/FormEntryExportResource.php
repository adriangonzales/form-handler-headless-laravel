<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FormEntryExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

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
            /**
             * A temporary signed link to the CSV, usable without the API token, e.g. as a plain browser
             * link. It expires a few minutes after this response (see `EXPORT_DOWNLOAD_URL_TTL`), so
             * fetch the export again for a fresh link. `null` until the export is completed.
             */
            'download_url' => $this->downloadUrl(),
            'completed_at' => $this->completed_at,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get a temporary signed download link, or null while the export is not completed.
     *
     * The signature covers only the path and query, so it still validates when the API is reached
     * through a proxy under a different host or scheme than the one that built the link.
     */
    protected function downloadUrl(): ?string
    {
        if (! $this->isCompleted()) {
            return null;
        }

        return url(URL::temporarySignedRoute(
            'entry-exports.download',
            now()->addMinutes(config()->integer('app.export_download_url_ttl')),
            $this->resource,
            absolute: false,
        ));
    }
}
