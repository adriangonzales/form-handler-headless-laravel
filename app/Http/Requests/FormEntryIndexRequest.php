<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonInterface;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FormEntryIndexRequest extends FormRequest
{
    /**
     * Columns the index can be sorted by. Prefix with "-" to sort descending, JSON:API style.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['created_at', 'spam_score'];

    /**
     * Filters accepted under the "filter" query parameter.
     *
     * @var list<string>
     */
    public const array FILTERS = ['read', 'starred', 'spam', 'created_from', 'created_to', 'trashed'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('view', $this->route('form'));
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
        $booleans = Rule::in(['true', 'false', '1', '0']);

        return [
            'sort' => ['sometimes', 'string', Rule::in($sorts)],
            'filter' => ['sometimes', 'array:'.implode(',', self::FILTERS)],
            'filter.read' => ['sometimes', $booleans],
            'filter.starred' => ['sometimes', $booleans],
            'filter.spam' => ['sometimes', $booleans],
            'filter.created_from' => ['sometimes', 'date_format:Y-m-d'],
            'filter.created_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:filter.created_from'],
            'filter.trashed' => ['sometimes', Rule::in(['with', 'only'])],
        ];
    }

    /**
     * Get a boolean filter's value, or null when the list is not filtered by it.
     *
     * @param  'read'|'starred'|'spam'  $filter
     */
    public function booleanFilter(string $filter): ?bool
    {
        return $this->has('filter.'.$filter) ? $this->boolean('filter.'.$filter) : null;
    }

    /**
     * Get the start of the first day to include, or null when unbounded.
     */
    public function createdFrom(): ?CarbonInterface
    {
        return $this->has('filter.created_from')
            ? $this->date('filter.created_from', 'Y-m-d')->startOfDay()
            : null;
    }

    /**
     * Get the end of the last day to include, or null when unbounded.
     */
    public function createdTo(): ?CarbonInterface
    {
        return $this->has('filter.created_to')
            ? $this->date('filter.created_to', 'Y-m-d')->endOfDay()
            : null;
    }

    /**
     * Get whether deleted entries are included ("with") or listed alone ("only"), or null to exclude them.
     *
     * @return 'with'|'only'|null
     */
    public function trashedFilter(): ?string
    {
        return match ($this->input('filter.trashed')) {
            'with' => 'with',
            'only' => 'only',
            default => null,
        };
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
