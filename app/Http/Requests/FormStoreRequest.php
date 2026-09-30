<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\FormSettingsValidationRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FormStoreRequest extends FormRequest
{
    use FormSettingsValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->fillDefaultHoneypotName();
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
        return [$this->honeypotNameCheck()];
    }
}
