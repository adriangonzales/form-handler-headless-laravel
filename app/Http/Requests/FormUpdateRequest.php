<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormUpdateRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users.id,id'],
            'name' => ['required', 'string', 'max:400'],
            'active' => ['required'],
            'schema' => ['nullable', 'json'],
            'settings' => ['nullable', 'json'],
        ];
    }
}
