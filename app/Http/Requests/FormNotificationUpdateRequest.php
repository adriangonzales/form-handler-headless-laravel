<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\FormNotificationValueRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FormNotificationUpdateRequest extends FormRequest
{
    use FormNotificationValueRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('notification'));
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
            'value' => $this->valueRules(),
            'enabled' => ['required'],
            'error' => ['prohibited'],
        ];
    }
}
