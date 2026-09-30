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
     * Allow access only to the owner of the entry's form.
     */
    private function ownsForm(User $user, FormEntry $formEntry): Response
    {
        return $user->id === $formEntry->form->user_id
            ? Response::allow()
            : Response::deny('You do not own this form.');
    }
}
