<?php

namespace App\Policies;

use App\Models\FormEntryExport;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FormEntryExportPolicy
{
    /**
     * Determine whether the user can view the export and download its file.
     */
    public function view(User $user, FormEntryExport $formEntryExport): Response
    {
        return $user->id === $formEntryExport->form?->user_id
            ? Response::allow()
            : Response::deny('You do not own this form.');
    }
}
