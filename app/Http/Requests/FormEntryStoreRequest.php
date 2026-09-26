<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormEntryStoreRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'form_id' => ['required', 'integer', 'exists:forms.id,id'],
            'ip' => ['nullable', 'string'],
            'ip_location_display' => ['nullable', 'string'],
            'referer' => ['nullable', 'string'],
            'user_agent' => ['nullable', 'string'],
            'user_agent_display' => ['nullable', 'string'],
            'spam' => ['nullable'],
            'spam_score' => ['required', 'numeric'],
            'spam_reason' => ['nullable', 'string'],
            'starred' => ['required'],
            'read_at' => ['nullable'],
            'data' => ['nullable', 'json'],
        ];
    }
}
