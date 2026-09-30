<?php

declare(strict_types=1);

namespace App\Actions\FormEntries;

use App\Models\Form;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class ApplyBulkAction
{
    /**
     * Apply a bulk action to the given entries of the form and return how many entries changed.
     * Triage actions skip entries already in the target state, so a repeated action changes nothing
     * and existing read times are kept.
     *
     * @param  list<string>  $ids
     */
    public function __invoke(Form $form, string $action, array $ids): int
    {
        $entries = $form->entries()->whereKey($ids);

        return match ($action) {
            'mark_read' => $entries->whereNull('read_at')->update(['read_at' => now()]),
            'mark_unread' => $entries->whereNotNull('read_at')->update(['read_at' => null]),
            'star' => $entries->where('starred', false)->update(['starred' => true]),
            'unstar' => $entries->where('starred', true)->update(['starred' => false]),
            'mark_spam' => $entries->where(
                fn (Builder $query) => $query->where('spam', false)->orWhereNull('spam')
            )->update(['spam' => true]),
            'mark_not_spam' => $entries->where(
                fn (Builder $query) => $query->where('spam', true)->orWhereNull('spam')
            )->update(['spam' => false]),
            'delete' => $entries->delete(),
            'restore' => $entries->onlyTrashed()->restore(),
            'force_delete' => $entries->onlyTrashed()->forceDelete(),
            default => throw new InvalidArgumentException("Unknown bulk action [{$action}]."),
        };
    }
}
