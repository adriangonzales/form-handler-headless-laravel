<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Models\Form;
use Illuminate\Http\Request;

class BuildValidationRules
{
    /**
     * Build validation rules from the form's schema, keyed by each field's input name (its `name`, or its ID).
     *
     * @return array<string, list<string>>
     */
    public function __invoke(Form $form, ?Request $request = null): array
    {
        $rules = [];

        foreach ($form->schema ?? [] as $fieldSettings) {
            $fieldRules = $fieldSettings['rules'] ?? ['sometimes'];

            if (is_string($fieldRules)) {
                $fieldRules = explode(',', $fieldRules);
            }

            $rules[$fieldSettings['name'] ?? $fieldSettings['id']] = $fieldRules;
        }

        return $rules;
    }
}
