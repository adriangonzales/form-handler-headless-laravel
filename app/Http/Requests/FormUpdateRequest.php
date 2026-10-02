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
            /**
             * A list of fields. Each has a unique ULID `id` and an integer `order` used to sort fields for
             * display, and may set a `label`, an input `name` (defaults to the ID) and Laravel validation
             * `rules` (an array or a comma-separated string). Other keys are rejected.
             *
             * @var list<array{id: string, order: int, label?: string, name?: string, rules?: list<string>|string}>|null
             */
            'schema' => ['nullable', 'list'],
            'schema.*' => ['array:id,order,label,name,rules'],
            'schema.*.id' => ['required', 'ulid', 'distinct'],
            'schema.*.order' => ['required', 'integer'],
            'schema.*.label' => ['nullable', 'string'],
            'schema.*.name' => ['nullable', 'string'],
            'schema.*.rules' => ['nullable'],
            'schema.*.rules.*' => ['string'],
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
        $form = $this->route('form');

        return [$this->honeypotNameCheck($form instanceof Form ? $form : null)];
    }
}
