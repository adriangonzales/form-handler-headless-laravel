<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FormEntryUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('entry'));
    }

    /**
     * What was submitted, and where from, is recorded at submission time and cannot be changed afterwards.
     *
     * @var list<string>
     */
    public const array SUBMISSION_FIELDS = ['input', 'ip', 'ip_location_display', 'referer', 'user_agent', 'user_agent_display'];

    /**
     * Get the validation rules that apply to the request. Only triage fields are editable, and each is
     * optional so a client can change one without resending the rest. Submission fields are rejected
     * rather than ignored so clients learn their change was not applied.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...array_fill_keys(self::SUBMISSION_FIELDS, ['prohibited']),
            'spam' => ['nullable', 'boolean'],
            'spam_score' => ['sometimes', 'required', 'numeric', 'between:0,9.999'],
            'spam_reason' => ['nullable', 'string'],
            'starred' => ['sometimes', 'required', 'boolean'],
            'read_at' => ['nullable', 'date'],
        ];
    }
}
