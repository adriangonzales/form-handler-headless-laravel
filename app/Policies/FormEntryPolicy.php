<?php

namespace App\Policies;

use App\Models\FormEntry;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FormEntryPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FormEntry $formEntry): Response
    {
        return $this->ownsForm($user, $formEntry);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FormEntry $formEntry): Response
    {
        return $this->ownsForm($user, $formEntry);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FormEntry $formEntry): Response
    {
        return $this->ownsForm($user, $formEntry);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FormEntry $formEntry): Response
    {
        return $this->ownsForm($user, $formEntry);
    }

    /**
     * Determine whether the user can permanently delete the model. Only entries already in the trash can be.
     */
    public function forceDelete(User $user, FormEntry $formEntry): Response
    {
        $ownsForm = $this->ownsForm($user, $formEntry);

        if ($ownsForm->denied()) {
            return $ownsForm;
        }

        return $formEntry->trashed()
            ? Response::allow()
            : Response::denyWithStatus(409, 'Only deleted entries can be permanently deleted.');
    }

    /**
     * Allow access only to the owner of the entry's form. Entries of a deleted form are inaccessible.
     */
    private function ownsForm(User $user, FormEntry $formEntry): Response
    {
        return $user->id === $formEntry->form?->user_id
            ? Response::allow()
            : Response::deny('You do not own this form.');
    }
}
