<?php

namespace App\Actions\Forms;

use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BuildValidationRules
{
    public function __invoke(Form $form, ?Request $request = null): array
    {
        $rules = [];

        // Build validation rules from Form Settings
        foreach ($form->schema as $fieldName => $fieldSettings) {
            $fieldRules = [];

            if (Arr::has($fieldSettings, 'rules')) {
                if (is_array($fieldSettings['rules'])) {
                    foreach ($fieldSettings['rules'] as $value) {
                        $fieldRules[] = $value;
                    }
                } else {
                    foreach (Str::of($fieldSettings['rules'])->explode(',') as $stringRule) {
                        $fieldRules[] = $stringRule;
                    }
                }
            } else {
                $fieldRules[] = 'sometimes';
            }

            $rules[Arr::get($fieldSettings, 'name', $fieldName)] = $fieldRules;
        }

        return $rules;
    }
}
