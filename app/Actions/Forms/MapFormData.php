<?php

namespace App\Actions\Forms;

use App\Models\FormEntry;
use Illuminate\Support\Arr;

class MapFormData
{
    public function __invoke(FormEntry $formEntry): array
    {
        $formEntry->loadMissing('form');

        $data = [];

        foreach ($formEntry->form->schema as $fieldId => $fieldSettings) {
            $data[$fieldId] = [
                'label' => Arr::get($fieldSettings, 'label', $fieldId),
                'data' => Arr::get($formEntry->data, $fieldId),
            ];
        }

        return $data;
    }
}
