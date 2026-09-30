<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Form;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FormEntryBulkRequest extends FormRequest
{
    /**
     * Actions that apply to entries which are not deleted.
     *
     * @var list<string>
     */
    public const array ACTIONS_FOR_ENTRIES = ['mark_read', 'mark_unread', 'star', 'unstar', 'mark_spam', 'mark_not_spam', 'delete'];

    /**
     * Actions that apply to deleted entries.
     *
     * @var list<string>
     */
    public const array ACTIONS_FOR_DELETED_ENTRIES = ['restore', 'force_delete'];

    /**
     * The most entries a single request may act on.
     */
    public const int MAX_IDS = 100;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('form'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Every ID must belong to the form and be in the right state for the action: deleted for
     * restore and force_delete, not deleted for everything else.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $form = $this->route('form');

        if (! $form instanceof Form) {
            abort(404);
        }

        $actsOnDeletedEntries = in_array($this->input('action'), self::ACTIONS_FOR_DELETED_ENTRIES, true);

        return [
            'action' => ['required', 'string', Rule::in([...self::ACTIONS_FOR_ENTRIES, ...self::ACTIONS_FOR_DELETED_ENTRIES])],
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('form_entries', 'id')
                    ->where('form_id', $form->id)
                    ->where(fn (Builder $query) => $actsOnDeletedEntries
                        ? $query->whereNotNull('deleted_at')
                        : $query->whereNull('deleted_at')),
            ],
        ];
    }
}
