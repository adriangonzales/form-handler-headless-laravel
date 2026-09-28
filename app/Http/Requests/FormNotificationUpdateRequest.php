<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FormNotificationUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'form_id' => ['required', 'integer', 'exists:forms.id,id'],
            'type' => ['required', 'in:email,sms'],
            'value' => ['required', 'string'],
            'enabled' => ['required'],
            'error' => ['nullable', 'string'],
        ];
    }
}
