<?php

namespace App\Http\Controllers;

use App\Events\FormEntryCreated;
use App\Http\Requests\FormEntryIndexRequest;
use App\Http\Requests\FormEntryStoreRequest;
use App\Http\Requests\FormEntryUpdateRequest;
use App\Http\Resources\FormEntryCollection;
use App\Http\Resources\FormEntryResource;
use App\Models\Form;
use App\Models\FormEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class FormEntryController extends Controller
{
    public function index(FormEntryIndexRequest $request, Form $form): FormEntryCollection
    {
        $read = $request->booleanFilter('read');
        $starred = $request->booleanFilter('starred');
        $spam = $request->booleanFilter('spam');
        $createdFrom = $request->createdFrom();
        $createdTo = $request->createdTo();

        $formEntries = $form->entries()
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
            ->orderBy('id', $request->sortDirection())
            ->paginate()
            ->withQueryString();

        return new FormEntryCollection($formEntries);
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
}
