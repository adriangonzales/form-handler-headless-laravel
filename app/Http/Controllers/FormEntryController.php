<?php

namespace App\Http\Controllers;

use App\Actions\FormEntries\ApplyBulkAction;
use App\Actions\FormEntries\FilterEntries;
use App\Events\FormEntryCreated;
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
use Illuminate\Support\Str;

class FormEntryController extends Controller
{
    public function index(FormEntryIndexRequest $request, Form $form, FilterEntries $filterEntries): FormEntryCollection
    {
        $formEntries = $filterEntries($form, $request->parameters())
            ->paginate()
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

    // TODO: Break this out into a separate inbound API-specific function
    public function store(FormEntryStoreRequest $request, Form $form): FormEntryResource
    {
        /** @var FormEntry */
        $formEntry = $form->entries()->create([
            'input' => $request->validated(),
            'ip' => implode(',', $request->ips()),
            // 'ip_location_display' => null, // TODO: Add parse step
            'referer' => Str::substr((string) $request->header('Referer'), 0, 255) ?: null,
            'user_agent' => $request->userAgent(),
            // 'user_agent_display', // TODO: Add parse step
            'spam' => false, // TODO: Add catpcha service step
            'spam_score' => 0, // TODO: Add catpcha service step
        ]);

        event(new FormEntryCreated($formEntry));

        return new FormEntryResource($formEntry);
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
