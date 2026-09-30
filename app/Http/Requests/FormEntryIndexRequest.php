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
     * Get the validated sort and filters, as accepted by FilterEntries.
     *
     * @return array{sort?: string, filter?: array<string, string>}
     */
    public function parameters(): array
    {
        return $this->safe()->only(['sort', 'filter']);
    }
}
