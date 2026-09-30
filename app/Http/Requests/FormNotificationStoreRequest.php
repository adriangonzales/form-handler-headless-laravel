<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\FormNotificationValueRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FormNotificationStoreRequest extends FormRequest
{
    use FormNotificationValueRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('form'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:email,sms'],
            'value' => $this->valueRules(),
            'enabled' => ['sometimes', 'boolean'],
            'error' => ['prohibited'],
        ];
    }
}
