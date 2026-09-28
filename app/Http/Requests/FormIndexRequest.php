<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormIndexRequest extends FormRequest
{
    /**
     * Sort values accepted by the index, JSON:API style: a leading "-" means descending.
     *
     * @var list<string>
     */
    public const array SORTS = ['created_at', '-created_at'];

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
            'sort' => ['sometimes', 'string', Rule::in(self::SORTS)],
        ];
    }

    /**
     * Get the column to sort by.
     */
    public function sortColumn(): string
    {
        return ltrim($this->sortValue(), '-');
    }

    /**
     * Get the sort direction, defaulting to oldest first.
     *
     * @return 'asc'|'desc'
     */
    public function sortDirection(): string
    {
        return str_starts_with($this->sortValue(), '-') ? 'desc' : 'asc';
    }

    private function sortValue(): string
    {
        return $this->string('sort', 'created_at')->toString();
    }
}
