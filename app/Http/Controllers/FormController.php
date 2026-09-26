<?php

namespace App\Http\Controllers;

use App\Events\FormCreated;
use App\Http\Requests\FormStoreRequest;
use App\Http\Requests\FormUpdateRequest;
use App\Http\Resources\FormCollection;
use App\Http\Resources\FormResource;
use App\Models\Form;

class FormController extends Controller
{
    public function index(): FormCollection
    {
        $forms = Form::query()->oldest()
            ->paginate();

        return new FormCollection($forms);
    }

    public function show(Form $form): FormResource
    {
        return new FormResource($form);
    }

    public function store(FormStoreRequest $request): FormResource
    {
        $form = Form::create($request->validated());

        event(new FormCreated($form));

        return new FormResource($form);
    }

    public function update(FormUpdateRequest $request, Form $form): FormResource
    {
        $form->update($request->validated());

        return new FormResource($form);
    }
}
