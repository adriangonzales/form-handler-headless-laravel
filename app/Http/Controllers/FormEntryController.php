<?php

namespace App\Http\Controllers;

use App\Actions\FormEntries\ApplyBulkAction;
use App\Actions\FormEntries\WriteEntriesCsv;
use App\Events\FormEntryCreated;
use App\Http\Requests\FormEntryBulkRequest;
use App\Http\Requests\FormEntryIndexRequest;
use App\Http\Requests\FormEntryStoreRequest;
use App\Http\Requests\FormEntryUpdateRequest;
use App\Http\Resources\FormEntryCollection;
use App\Http\Resources\FormEntryResource;
use App\Models\Form;
use App\Models\FormEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormEntryController extends Controller
{
    public function index(FormEntryIndexRequest $request, Form $form): FormEntryCollection
    {
        $formEntries = $this->filteredEntries($request, $form)
            ->paginate()
            ->withQueryString();

        return new FormEntryCollection($formEntries);
    }

    /**
     * Stream the form's entries as CSV, honouring the same filters and sort as the index.
     */
    public function export(FormEntryIndexRequest $request, Form $form, WriteEntriesCsv $writeEntriesCsv): StreamedResponse
    {
        $entries = $this->filteredEntries($request, $form)->cursor();
        $filename = (Str::slug($form->name) ?: 'form').'-entries-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($form, $entries, $writeEntriesCsv): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new RuntimeException('Unable to open the output stream.');
            }

            $writeEntriesCsv($form, $entries, $handle);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
            'referer' => $request->header('HTTP_REFERER'),
            'user_agent' => $request->userAgent(),
            // 'user_agent_display', // TODO: Add parse step
            'spam' => false, // TODO: Add catpcha service step
            'spam_score' => 0, // TODO: Add catpcha service step
        ]);

        event(new FormEntryCreated($formEntry));

        // $form->user->notify(new NewFormEntry($formEntry));
        // Mail::to($form->user)->send(new NewFormEntry($formEntry));

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

    /**
     * Build the form's entry query from the index filters and sort.
     *
     * @return Builder<FormEntry>
     */
    private function filteredEntries(FormEntryIndexRequest $request, Form $form): Builder
    {
        $read = $request->booleanFilter('read');
        $starred = $request->booleanFilter('starred');
        $spam = $request->booleanFilter('spam');
        $createdFrom = $request->createdFrom();
        $createdTo = $request->createdTo();
        $trashed = $request->trashedFilter();

        return $form->entries()->getQuery()
            ->when($trashed === 'with', fn (Builder $query) => $query->withTrashed())
            ->when($trashed === 'only', fn (Builder $query) => $query->onlyTrashed())
            ->when($read === true, fn (Builder $query) => $query->whereNotNull('read_at'))
            ->when($read === false, fn (Builder $query) => $query->whereNull('read_at'))
            ->when($starred !== null, fn (Builder $query) => $query->where('starred', $starred))
            ->when($spam === true, fn (Builder $query) => $query->where('spam', true))
            ->when($spam === false, fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('spam', false)->orWhereNull('spam')
            ))
            ->when($createdFrom !== null, fn (Builder $query) => $query->where('created_at', '>=', $createdFrom))
            ->when($createdTo !== null, fn (Builder $query) => $query->where('created_at', '<=', $createdTo))
            ->orderBy($request->sortColumn(), $request->sortDirection())
            ->orderBy('id', $request->sortDirection());
    }
}
