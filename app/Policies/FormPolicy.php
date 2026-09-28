<?php

namespace App\Policies;

use App\Models\Form;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FormPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Form $form): Response
    {
        return $user->id === $form->user_id
            ? Response::allow()
            : Response::deny('You do not own this form.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Form $form): Response
    {
        return $user->id === $form->user_id
            ? Response::allow()
            : Response::deny('You do not own this form.');
    }

    /**
     * Determine whether an entry can be submitted to the form.
     */
    public function submit(?User $user, Form $form): Response
    {
        return $form->active
            ? Response::allow()
            : Response::deny('This form is not accepting submissions.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Form $form): Response
    {
        return $user->id === $form->user_id
            ? Response::allow()
            : Response::deny('You do not own this form.');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Form $form): Response
    {
        return $user->id === $form->user_id
            ? Response::allow()
            : Response::deny('You do not own this form.');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Form $form): bool
    {
        return false;
    }
}
