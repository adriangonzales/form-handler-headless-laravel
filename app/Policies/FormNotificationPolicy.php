<?php

namespace App\Policies;

use App\Models\FormNotification;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FormNotificationPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FormNotification $formNotification): Response
    {
        return $this->ownsForm($user, $formNotification);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FormNotification $formNotification): Response
    {
        return $this->ownsForm($user, $formNotification);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FormNotification $formNotification): Response
    {
        return $this->ownsForm($user, $formNotification);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FormNotification $formNotification): Response
    {
        return $this->ownsForm($user, $formNotification);
    }

    /**
     * Allow access only to the owner of the recipient's form. Recipients of a deleted form are inaccessible.
     */
    private function ownsForm(User $user, FormNotification $formNotification): Response
    {
        return $user->id === $formNotification->form?->user_id
            ? Response::allow()
            : Response::deny('You do not own this form.');
    }
}
