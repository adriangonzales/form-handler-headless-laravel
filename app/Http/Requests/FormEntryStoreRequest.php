<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Forms\BuildValidationRules;
use App\Models\Form;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FormEntryStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('submit', $this->route('form'));
    }

    /**
     * Get the validation rules that apply to the request: those built from the form's schema. Route
     * model binding guarantees the form; it is only missing when the API docs evaluate these rules
     * outside a request, and the fields depend on each form's schema anyway.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $form = $this->route('form');

        return $form instanceof Form ? (new BuildValidationRules)($form) : [];
    }
}
