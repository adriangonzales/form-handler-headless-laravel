<?php

namespace App\Http\Controllers;

use App\Events\FormCreated;
use App\Http\Requests\FormStoreRequest;
use App\Http\Requests\FormUpdateRequest;
use App\Http\Resources\FormCollection;
use App\Http\Resources\FormResource;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FormController extends Controller
{
    public function index(Request $request): FormCollection
    {
        $forms = $request->user()->forms()
            ->oldest()
            ->paginate();

        return new FormCollection($forms);
    }

    public function show(Form $form): FormResource
    {
        Gate::authorize('view', $form);

        return new FormResource($form);
    }

    public function store(FormStoreRequest $request): FormResource
    {
        $form = $request->user()->forms()->create($request->validated());

        event(new FormCreated($form));

        return new FormResource($form);
    }

    public function update(FormUpdateRequest $request, Form $form): FormResource
    {
        $form->update($request->validated());

        return new FormResource($form);
    }
}
