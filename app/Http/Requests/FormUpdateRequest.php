<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\FormSettingsValidationRules;
use App\Models\Form;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FormUpdateRequest extends FormRequest
{
    use FormSettingsValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('form'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $form = $this->route('form');

        $this->fillDefaultHoneypotName($form instanceof Form ? $form : null);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:400'],
            'active' => ['required', 'boolean'],
            'schema' => ['nullable', 'array'],
            ...$this->settingsRules(),
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, Closure>
     */
    public function after(): array
    {
        $form = $this->route('form');

        return [$this->honeypotNameCheck($form instanceof Form ? $form : null)];
    }
}
