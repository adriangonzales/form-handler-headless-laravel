<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormEntryIndexRequest;
use App\Http\Resources\FormEntryExportResource;
use App\Jobs\GenerateFormEntryExport;
use App\Models\Form;
use App\Models\FormEntryExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormEntryExportController extends Controller
{
    /**
     * Queue a CSV export of the form's entries, using the same filters and sort as the entry index.
     */
    public function store(FormEntryIndexRequest $request, Form $form): JsonResponse
    {
        $export = $form->entryExports()->create([
            'status' => FormEntryExport::STATUS_PENDING,
            'parameters' => $request->parameters(),
            'disk' => config('filesystems.default'),
            'filename' => (Str::slug($form->name) ?: 'form').'-entries-'.now()->format('Y-m-d').'.csv',
            'expires_at' => now()->addHours(FormEntryExport::RETENTION_HOURS),
        ]);

        dispatch(new GenerateFormEntryExport($export));

        return (new FormEntryExportResource($export->fresh()))
            ->response()
            ->setStatusCode(202)
            ->header('Location', route('entry-exports.show', $export));
    }

    public function show(FormEntryExport $export): FormEntryExportResource
    {
        Gate::authorize('view', $export);

        return new FormEntryExportResource($export);
    }

    public function download(FormEntryExport $export): StreamedResponse
    {
        Gate::authorize('view', $export);

        abort_if($export->isExpired(), 410, 'This export has expired.');
        abort_unless($export->isCompleted() && $export->path !== null, 409, 'This export is not ready.');

        return Storage::disk($export->disk)->download($export->path, $export->filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
