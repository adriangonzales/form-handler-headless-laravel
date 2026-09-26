<?php

namespace App\Http\Controllers;

use App\Events\FormEntryCreated;
use App\Http\Requests\FormEntryStoreRequest;
use App\Http\Requests\FormEntryUpdateRequest;
use App\Http\Resources\FormEntryCollection;
use App\Http\Resources\FormEntryResource;
use App\Models\Form;
use App\Models\FormEntry;

class FormEntryController extends Controller
{
    public function index(): FormEntryCollection
    {
        $formEntries = FormEntry::query()->oldest()
            ->paginate();

        return new FormEntryCollection($formEntries);
    }

    public function show(FormEntry $entry): FormEntryResource
    {
        return new FormEntryResource($entry);
    }

    // TODO: Break this out into a separate inbound API-specific function
    public function store(FormEntryStoreRequest $request, Form $form): FormEntryResource
    {
        /** @var FormEntry */
        $formEntry = $form->entries()->create([
            'data' => $request->validated(),
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
