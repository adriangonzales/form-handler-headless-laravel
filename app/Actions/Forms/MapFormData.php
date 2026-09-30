<?php

namespace App\Actions\Forms;

use App\Models\FormEntry;
use Illuminate\Support\Arr;

class MapFormData
{
    /**
     * Pair each schema field's label with the submitted value. Values are stored under the field's
     * "name" when it has one, otherwise under its schema key (see BuildValidationRules).
     *
     * @return array<string, array{label: string, data: mixed}>
     */
    public function __invoke(FormEntry $formEntry): array
    {
        $formEntry->loadMissing('form');

        $data = [];

        foreach ($formEntry->form->schema ?? [] as $fieldId => $fieldSettings) {
            $data[$fieldId] = [
                'label' => $fieldSettings['label'] ?? $fieldId,
                'data' => Arr::get($formEntry->input ?? [], $fieldSettings['name'] ?? $fieldId),
            ];
        }

        return $data;
    }
}
