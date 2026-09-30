<?php

namespace App\Actions\Forms;

use App\Models\FormEntry;
use Illuminate\Support\Arr;

class MapFormData
{
    /**
     * @return array<string, array{label: string, data: mixed}>
     */
    public function __invoke(FormEntry $formEntry): array
    {
        $formEntry->loadMissing('form');

        $data = [];

        foreach ($formEntry->form->schema ?? [] as $fieldId => $fieldSettings) {
            $data[$fieldId] = [
                'label' => $fieldSettings['label'] ?? $fieldId,
                'data' => Arr::get($formEntry->input ?? [], $fieldId),
            ];
        }

        return $data;
    }
}
