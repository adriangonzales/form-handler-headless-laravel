<?php

namespace App\Http\Controllers;

use App\Events\FormEntryCreated;
use App\Http\Requests\FormEntryStoreRequest;
use App\Http\Requests\FormEntryUpdateRequest;
use App\Http\Resources\FormEntryCollection;
use App\Http\Resources\FormEntryResource;
use App\Mail\NewFormEntry;
use App\Models\Form;
use App\Models\FormEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class FormEntryController extends Controller
{
    public function index(): FormEntryCollection
    {
        $formEntries = FormEntry::query()->oldest()
            ->paginate();

        return new FormEntryCollection($formEntries);
    }

    public function show(FormEntry $formEntry): FormEntryResource
    {
        return new FormEntryResource($formEntry);
    }

    // TODO: Break this out into a separate inbound API-specific function
    public function store(FormEntryStoreRequest $request, Form $form): FormEntryResource
    {
        $formEntry = FormEntry::create($request->validated());

        event(new \App\Events\FormEntryCreated($formEntry));

        $form->user->notify(new NewFormEntry($formEntry));

        Mail::to($form->user)->send(new NewFormEntry($formEntry));

        return new FormEntryResource($formEntry);
    }

    public function update(FormEntryUpdateRequest $request, FormEntry $formEntry): FormEntryResource
    {
        $formEntry->update($request->validated());

        return new FormEntryResource($formEntry->fresh());
    }
}
