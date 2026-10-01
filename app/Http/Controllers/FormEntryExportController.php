<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormEntryExportIndexRequest;
use App\Http\Requests\FormEntryIndexRequest;
use App\Http\Resources\FormEntryExportCollection;
use App\Http\Resources\FormEntryExportResource;
use App\Jobs\GenerateFormEntryExport;
use App\Models\Form;
use App\Models\FormEntryExport;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormEntryExportController extends Controller
{
    /**
     * List the exports of the user's forms, newest first, with their status.
     *
     * Exports past their `expires_at` are left out, as are exports of deleted forms.
     */
    public function index(FormEntryExportIndexRequest $request): FormEntryExportCollection
    {
        $exports = $request->user()->entryExports()
            ->where('form_entry_exports.expires_at', '>', now())
            ->latest('form_entry_exports.created_at')
            ->latest('form_entry_exports.id')
            ->paginate($request->perPage())
            ->withQueryString();

        return new FormEntryExportCollection($exports);
    }

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

    /**
     * Download a completed export's CSV.
     *
     * Reached through the temporary signed `download_url` from an export response, not the API token:
     * the signature shows the link was issued to a user allowed to view the export. A missing, altered
     * or expired signature returns 403.
     *
     * @unauthenticated
     */
    #[Response(403, 'Missing, invalid or expired signature', type: 'array{message: string}', examples: [['message' => 'Invalid signature.']])]
    public function download(FormEntryExport $export): StreamedResponse
    {
        abort_if($export->isExpired(), 410, 'This export has expired.');
        abort_unless($export->isCompleted() && $export->path !== null, 409, 'This export is not ready.');

        return Storage::disk($export->disk)->download($export->path, $export->filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
