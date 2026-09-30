<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormIndexRequest extends FormRequest
{
    /**
     * Columns the index can be sorted by. Prefix with "-" to sort descending, JSON:API style.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['created_at', 'updated_at', 'name'];

    /**
     * Filters accepted under the "filter" query parameter.
     *
     * @var list<string>
     */
    public const array FILTERS = ['active'];

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
        $sorts = collect(self::SORTABLE)
            ->flatMap(fn (string $column): array => [$column, '-'.$column])
            ->all();

        return [
            'sort' => ['sometimes', 'string', Rule::in($sorts)],
            'filter' => ['sometimes', 'array:'.implode(',', self::FILTERS)],
            'filter.active' => ['sometimes', Rule::in(['true', 'false', '1', '0'])],
        ];
    }

    /**
     * Get the requested "active" filter, or null when the list is not filtered by it.
     */
    public function activeFilter(): ?bool
    {
        return $this->has('filter.active') ? $this->boolean('filter.active') : null;
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
