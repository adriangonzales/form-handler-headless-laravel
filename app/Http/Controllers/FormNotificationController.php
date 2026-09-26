<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormNotificationStoreRequest;
use App\Http\Requests\FormNotificationUpdateRequest;
use App\Http\Resources\FormNotificationCollection;
use App\Http\Resources\FormNotificationResource;
use App\Models\Form;
use App\Models\FormNotification;

class FormNotificationController extends Controller
{
    public function index(Form $form): FormNotificationCollection
    {
        return new FormNotificationCollection($form->notifications()->paginate());
    }

    public function show(FormNotification $notification): FormNotificationResource
    {
        return new FormNotificationResource($notification);
    }

    public function store(FormNotificationStoreRequest $request, Form $form): FormNotificationResource
    {
        $notification = $form->notifications()->create($request->validated());

        return new FormNotificationResource($notification);
    }

    public function update(FormNotificationUpdateRequest $request, FormNotification $notification): FormNotificationResource
    {
        $notification->update($request->validated());

        return new FormNotificationResource($notification->fresh());
    }
}
