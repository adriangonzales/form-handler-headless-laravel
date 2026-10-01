<?php

namespace App\Http\Controllers;

use App\Actions\FormEntries\CreateFormEntry;
use App\Data\FormSettings;
use App\Events\FormEntrySubmitted;
use App\Http\Requests\FormSubmissionRequest;
use App\Models\Form;
use Illuminate\Http\JsonResponse;

class FormSubmissionController extends Controller
{
    /**
     * Accept a public, unauthenticated submission to a form.
     *
     * The body fields are the form's schema input names (each field's `name`, or its ID), validated with
     * the field's rules. Unknown fields are dropped.
     *
     * Responds with the form's `redirect` and `message` settings so the client can show the
     * success message or navigate to the redirect itself; no 3XX redirect is ever sent.
     *
     * A submission that fills in the form's honeypot field gets the same response, so bots cannot
     * tell, but is stored flagged as spam. Other submissions are checked for spam before the form's
     * recipients are alerted.
     *
     * @unauthenticated
     */
    public function __invoke(FormSubmissionRequest $request, Form $form, CreateFormEntry $createFormEntry): JsonResponse
    {
        $settings = $form->settings ?? new FormSettings;

        $formEntry = $createFormEntry(
            $form,
            $request->validated(),
            $request,
            $settings->honeypotTripped($request->all()) ? 'Honeypot field was filled in.' : null,
        );

        event(new FormEntrySubmitted($formEntry));

        return response()->json([
            'data' => [
                'redirect' => $settings->redirect,
                'message' => $settings->message,
            ],
        ], 201);
    }
}
