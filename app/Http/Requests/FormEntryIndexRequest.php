<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FormEntryIndexRequest extends FormRequest
{
    /**
     * Largest page size a client may request with "per_page".
     */
    public const int MAX_PER_PAGE = 100;

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
            /** Results per page, from 1 to 100. Defaults to 15. Ignored when requesting an export. */
            'per_page' => ['sometimes', 'integer', 'between:1,'.self::MAX_PER_PAGE],
            'sort' => ['sometimes', 'string', Rule::in($sorts)],
            /**
             * Every filter is optional. Unknown keys are rejected.
             *
             * @var array{read?: string, starred?: string, spam?: string, created_from?: string, created_to?: string, trashed?: string}
             */
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
     * Get the validated sort and filters, as accepted by FilterEntries.
     *
     * @return array{sort?: string, filter?: array<string, string>}
     */
    public function parameters(): array
    {
        return $this->safe()->only(['sort', 'filter']);
    }

    /**
     * Get the requested page size, defaulting to 15.
     */
    public function perPage(): int
    {
        return $this->integer('per_page', 15);
    }
}
