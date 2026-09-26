<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormNotificationStoreRequest;
use App\Http\Requests\FormNotificationUpdateRequest;
use App\Http\Resources\FormNotificationCollection;
use App\Http\Resources\FormNotificationResource;
use App\Models\Form;
use App\Models\FormNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FormNotificationController extends Controller
{
    public function index(Form $form): FormNotificationCollection
    {
        return new FormNotificationCollection($form->notifications()->paginate());
    }

    public function show(FormNotification $formNotification): FormNotificationResource
    {
        return new FormNotificationResource($formNotification);
    }

    public function store(FormNotificationStoreRequest $request, Form $form): FormNotificationResource
    {
        $formNotification = $form->notifications()->create($request->validated());

        return new FormNotificationResource($formNotification);
    }

    public function update(FormNotificationUpdateRequest $request, FormNotification $formNotification): FormNotificationResource
    {
        $formNotification->update($request->validated());

        return new FormNotificationResource($formNotification->fresh());
    }
}
