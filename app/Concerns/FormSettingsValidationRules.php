<?php

namespace App\Concerns;

use App\Data\FormSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Spatie\LaravelData\Support\DataConfig;

trait FormSettingsValidationRules
{
    /**
     * Get the validation rules for a form's settings, derived from the FormSettings data object.
     *
     * Unknown settings keys are rejected rather than silently dropped.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function settingsRules(): array
    {
        $allowedKeys = resolve(DataConfig::class)
            ->getDataClass(FormSettings::class)
            ->properties
            ->keys()
            ->implode(',');

        $rules = [
            'settings' => ['nullable', 'array:' . $allowedKeys],
        ];

        $settings = $this->input('settings');

        if (is_array($settings)) {
            foreach (FormSettings::getValidationRules($settings) as $key => $settingRules) {
                $rules['settings.' . $key] = $settingRules;
            }
        }

        return $rules;
    }
}
