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
     * Get the validation rules that apply to the request. A recipient belongs to one form for life, and
     * error is reported by the system, so both are rejected rather than ignored.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'form_id' => ['prohibited'],
            'type' => ['required', 'in:email,sms'],
            'value' => ['required', 'string'],
            'enabled' => ['required'],
            'error' => ['prohibited'],
        ];
    }
}
