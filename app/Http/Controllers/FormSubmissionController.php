<?php

namespace App\Http\Controllers;

use App\Actions\FormEntries\CreateFormEntry;
use App\Data\FormSettings;
use App\Http\Requests\FormSubmissionRequest;
use App\Models\Form;
use Illuminate\Http\JsonResponse;

class FormSubmissionController extends Controller
{
    /**
     * Accept a public, unauthenticated submission to a form.
     *
     * Responds with the form's `redirect` and `message` settings so the client can show the
     * success message or navigate to the redirect itself; no 3XX redirect is ever sent.
     *
     * @unauthenticated
     */
    public function __invoke(FormSubmissionRequest $request, Form $form, CreateFormEntry $createFormEntry): JsonResponse
    {
        $createFormEntry($form, $request->validated(), $request);

        $settings = $form->settings ?? new FormSettings;

        return response()->json([
            'data' => [
                'redirect' => $settings->redirect,
                'message' => $settings->message,
            ],
        ], 201);
    }
}
