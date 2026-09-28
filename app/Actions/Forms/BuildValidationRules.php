<?php

namespace App\Actions\Forms;

use App\Models\Form;
use Illuminate\Http\Request;

class BuildValidationRules
{
    /**
     * Build validation rules from the form's schema, keyed by field name.
     *
     * @return array<string, list<string>>
     */
    public function __invoke(Form $form, ?Request $request = null): array
    {
        $rules = [];

        foreach ($form->schema ?? [] as $fieldName => $fieldSettings) {
            $fieldRules = $fieldSettings['rules'] ?? ['sometimes'];

            if (is_string($fieldRules)) {
                $fieldRules = explode(',', $fieldRules);
            }

            $rules[$fieldSettings['name'] ?? $fieldName] = $fieldRules;
        }

        return $rules;
    }
}
