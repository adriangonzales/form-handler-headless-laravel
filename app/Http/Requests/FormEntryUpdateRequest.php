<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FormEntryUpdateRequest extends FormRequest
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
            'input' => ['nullable', 'json'],
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
        ];
    }
}
