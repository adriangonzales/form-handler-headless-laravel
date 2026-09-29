<?php

namespace App\Actions\Forms;

use App\Events\FormCreated;
use App\Models\Form;
use Illuminate\Support\Str;

class DuplicateForm
{
    /**
     * Copy a form's name, schema, and settings into a new, inactive form.
     */
    public function __invoke(Form $form): Form
    {
        $copy = $form->replicate()->fill([
            'name' => Str::limit($form->name, 393, '').' (copy)',
            'active' => false,
        ]);
        $copy->save();

        event(new FormCreated($copy));

        return $copy;
    }
}
