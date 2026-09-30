<?php

namespace App\Http\Controllers;

use App\Actions\Forms\DuplicateForm;
use App\Events\FormCreated;
use App\Http\Requests\FormIndexRequest;
use App\Http\Requests\FormStoreRequest;
use App\Http\Requests\FormUpdateRequest;
use App\Http\Resources\FormCollection;
use App\Http\Resources\FormResource;
use App\Models\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FormController extends Controller
{
    public function index(FormIndexRequest $request): FormCollection
    {
        $activeFilter = $request->activeFilter();
        $sortColumn = $request->sortColumn() === 'name'
            ? DB::raw('lower(name)')
            : $request->sortColumn();

        $forms = $request->user()->forms()
            ->when($activeFilter !== null, fn (Builder $query) => $query->where('active', $activeFilter))
            ->orderBy($sortColumn, $request->sortDirection())
            ->orderBy('id', $request->sortDirection())
            ->paginate()
            ->withQueryString();

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

    public function destroy(Form $form): Response
    {
        Gate::authorize('delete', $form);

        $form->delete();

        return response()->noContent();
    }

    public function restore(Form $form): FormResource
    {
        Gate::authorize('restore', $form);

        $form->restore();

        return new FormResource($form);
    }

    /**
     * Copy a form's name, schema, and settings into a new, inactive form.
     */
    public function duplicate(Form $form, DuplicateForm $duplicateForm): JsonResponse
    {
        Gate::authorize('view', $form);

        $copy = $duplicateForm($form);

        return (new FormResource($copy))->response()->setStatusCode(201);
    }
}
