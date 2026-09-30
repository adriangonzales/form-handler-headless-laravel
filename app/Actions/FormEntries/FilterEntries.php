<?php

declare(strict_types=1);

namespace App\Actions\FormEntries;

use App\Models\Form;
use App\Models\FormEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

class FilterEntries
{
    /**
     * Build a query for the form's entries from validated index parameters (see FormEntryIndexRequest).
     *
     * @param  array{sort?: string, filter?: array<string, string>}  $parameters
     * @return Builder<FormEntry>
     */
    public function __invoke(Form $form, array $parameters): Builder
    {
        $filter = $parameters['filter'] ?? [];
        $sort = $parameters['sort'] ?? 'created_at';
        $sortColumn = ltrim($sort, '-');
        $sortDirection = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $read = $this->booleanFilter($filter, 'read');
        $starred = $this->booleanFilter($filter, 'starred');
        $spam = $this->booleanFilter($filter, 'spam');
        $createdFrom = isset($filter['created_from']) ? Date::createFromFormat('Y-m-d', $filter['created_from'])?->startOfDay() : null;
        $createdTo = isset($filter['created_to']) ? Date::createFromFormat('Y-m-d', $filter['created_to'])?->endOfDay() : null;
        $trashed = $filter['trashed'] ?? null;

        return $form->entries()->getQuery()
            ->when($trashed === 'with', fn (Builder $query) => $query->withTrashed())
            ->when($trashed === 'only', fn (Builder $query) => $query->onlyTrashed())
            ->when($read === true, fn (Builder $query) => $query->whereNotNull('read_at'))
            ->when($read === false, fn (Builder $query) => $query->whereNull('read_at'))
            ->when($starred !== null, fn (Builder $query) => $query->where('starred', $starred))
            ->when($spam === true, fn (Builder $query) => $query->where('spam', true))
            ->when($spam === false, fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('spam', false)->orWhereNull('spam')
            ))
            ->when($createdFrom !== null, fn (Builder $query) => $query->where('created_at', '>=', $createdFrom))
            ->when($createdTo !== null, fn (Builder $query) => $query->where('created_at', '<=', $createdTo))
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', $sortDirection);
    }

    /**
     * Get a boolean filter's value, or null when the list is not filtered by it.
     *
     * @param  array<string, string>  $filter
     */
    private function booleanFilter(array $filter, string $name): ?bool
    {
        return isset($filter[$name]) ? filter_var($filter[$name], FILTER_VALIDATE_BOOL) : null;
    }
}
