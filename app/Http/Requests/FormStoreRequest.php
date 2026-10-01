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
            /**
             * Fields keyed by field ID. Each may set a `label`, an input `name` (defaults to the ID) and
             * Laravel validation `rules` (an array or a comma-separated string).
             *
             * @var array<string, array{label?: string, name?: string, rules?: list<string>|string}>|null
             */
            'schema' => ['nullable', 'array'],
            /**
             * Every key is optional and takes its default when omitted. Unknown keys are rejected.
             *
             * @var array{redirect?: string|null, timezone?: string|null, domains?: list<string>|null, message?: string|null, honeypot_enabled?: bool, honeypot_name?: string|null}|null
             */
            'settings' => $this->settingsRule(),
            ...$this->settingsFieldRules(),
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
