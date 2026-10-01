<?php

namespace App\Http\Controllers;

use App\Actions\FormEntries\ApplyBulkAction;
use App\Actions\FormEntries\CreateFormEntry;
use App\Actions\FormEntries\FilterEntries;
use App\Http\Requests\FormEntryBulkRequest;
use App\Http\Requests\FormEntryIndexRequest;
use App\Http\Requests\FormEntryStoreRequest;
use App\Http\Requests\FormEntryUpdateRequest;
use App\Http\Resources\FormEntryCollection;
use App\Http\Resources\FormEntryResource;
use App\Models\Form;
use App\Models\FormEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class FormEntryController extends Controller
{
    public function index(FormEntryIndexRequest $request, Form $form, FilterEntries $filterEntries): FormEntryCollection
    {
        $formEntries = $filterEntries($form, $request->parameters())
            ->paginate($request->perPage())
            ->withQueryString();

        return new FormEntryCollection($formEntries);
    }

    public function bulk(FormEntryBulkRequest $request, Form $form, ApplyBulkAction $applyBulkAction): JsonResponse
    {
        $action = $request->validated('action');
        $affected = $applyBulkAction($form, $action, $request->validated('ids'));

        return response()->json(['data' => ['action' => $action, 'affected' => $affected]]);
    }

    public function show(FormEntry $entry): FormEntryResource
    {
        Gate::authorize('view', $entry);

        return new FormEntryResource($entry);
    }

    public function store(FormEntryStoreRequest $request, Form $form, CreateFormEntry $createFormEntry): FormEntryResource
    {
        return new FormEntryResource($createFormEntry($form, $request->validated(), $request));
    }

    public function update(FormEntryUpdateRequest $request, FormEntry $entry): FormEntryResource
    {
        $entry->update($request->validated());

        return new FormEntryResource($entry->fresh());
    }

    public function destroy(FormEntry $entry): Response
    {
        Gate::authorize('delete', $entry);

        $entry->delete();

        return response()->noContent();
    }

    public function restore(FormEntry $entry): FormEntryResource
    {
        Gate::authorize('restore', $entry);

        $entry->restore();

        return new FormEntryResource($entry);
    }

    public function forceDestroy(FormEntry $entry): Response
    {
        Gate::authorize('forceDelete', $entry);

        $entry->forceDelete();

        return response()->noContent();
    }
}
