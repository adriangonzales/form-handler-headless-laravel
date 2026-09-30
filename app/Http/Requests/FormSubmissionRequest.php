<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Forms\BuildValidationRules;
use App\Data\FormSettings;
use App\Models\Form;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FormSubmissionRequest extends FormRequest
{
    /**
     * Determine if the public submission is allowed: the form must be active and, when the form
     * restricts domains, the Referer header must match one of them.
     */
    public function authorize(): Response
    {
        $form = $this->form();
        $response = Gate::inspect('submit', $form);

        if ($response->denied()) {
            return $response;
        }

        $settings = $form->settings ?? new FormSettings;

        return $settings->allowsReferer($this->header('Referer'))
            ? Response::allow()
            : Response::deny('Submissions are not accepted from this domain.');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return (new BuildValidationRules)($this->form());
    }

    /**
     * Get the form being submitted to.
     */
    public function form(): Form
    {
        $form = $this->route('form');

        if (! $form instanceof Form) {
            abort(404);
        }

        return $form;
    }
}
