<?php

namespace App\Concerns;

use App\Actions\Forms\GenerateHoneypotName;
use App\Data\FormSettings;
use App\Models\Form;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Support\DataConfig;

trait FormSettingsValidationRules
{
    /**
     * Get the rule for the settings object itself: nullable, and limited to the keys defined by the
     * FormSettings data object so unknown keys are rejected rather than silently dropped.
     *
     * Kept apart from settingsFieldRules() so each request can document the `settings` key's type for
     * the API docs, which only read docblocks on keys written directly in rules().
     *
     * @return list<string>
     */
    protected function settingsRule(): array
    {
        $allowedKeys = resolve(DataConfig::class)
            ->getDataClass(FormSettings::class)
            ->properties
            ->keys()
            ->implode(',');

        return ['nullable', 'array:'.$allowedKeys];
    }

    /**
     * Get the validation rules for each sent setting, derived from the FormSettings data object.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function settingsFieldRules(): array
    {
        $settings = $this->input('settings');

        if (! is_array($settings)) {
            return [];
        }

        $rules = [];

        foreach (FormSettings::getValidationRules($settings) as $key => $settingRules) {
            $rules['settings.'.$key] = $settingRules;
        }

        return $rules;
    }

    /**
     * Fill in a honeypot name when settings are sent with the honeypot enabled but no name. The form's
     * stored name is kept if it has one, so a site's hidden input keeps working; otherwise a new one is
     * generated that does not match any of the schema's input names. Run before validation.
     */
    protected function fillDefaultHoneypotName(?Form $form = null): void
    {
        $settings = $this->input('settings');

        if (! is_array($settings) || ! in_array($settings['honeypot_enabled'] ?? false, [true, 1, '1'], true)) {
            return;
        }

        if (($settings['honeypot_name'] ?? null) !== null && $settings['honeypot_name'] !== '') {
            return;
        }

        $schema = $this->has('schema') ? $this->input('schema') : $form?->schema;
        $schemaInputNames = is_array($schema) ? $this->schemaInputNames($schema) : [];
        $storedName = $form?->settings?->honeypot_name;

        $settings['honeypot_name'] = $storedName !== null && ! in_array($storedName, $schemaInputNames, true)
            ? $storedName
            : (new GenerateHoneypotName)($schemaInputNames);

        $this->merge(['settings' => $settings]);
    }

    /**
     * Get an after-validation check that rejects a honeypot name matching one of the schema's input
     * names, since every real submission filling in that field would be flagged as spam.
     *
     * Settings or schema omitted from the request fall back to the form's stored values. The error is
     * reported on the honeypot name when settings were sent, otherwise on the schema.
     *
     * @return Closure(Validator): void
     */
    protected function honeypotNameCheck(?Form $form = null): Closure
    {
        return function (Validator $validator) use ($form): void {
            $settingsSent = $this->has('settings');
            $settings = $settingsSent ? $this->input('settings') : $form?->settings?->toArray();
            $schema = $this->has('schema') ? $this->input('schema') : $form?->schema;
            $honeypotName = is_array($settings) ? ($settings['honeypot_name'] ?? null) : null;

            if (! is_string($honeypotName) || ! is_array($schema)) {
                return;
            }

            if (! in_array($honeypotName, $this->schemaInputNames($schema), true)) {
                return;
            }

            if ($settingsSent) {
                $validator->errors()->add('settings.honeypot_name', 'The honeypot name must not match a schema field.');
            } else {
                $validator->errors()->add('schema', 'The schema must not contain a field named after the honeypot.');
            }
        };
    }

    /**
     * Get the input names a schema accepts, matching BuildValidationRules: a field's `name`, falling back to its ID.
     *
     * @param  array<mixed>  $schema
     * @return list<string>
     */
    private function schemaInputNames(array $schema): array
    {
        $inputNames = [];

        foreach ($schema as $field) {
            if (! is_array($field)) {
                continue;
            }

            $inputName = $field['name'] ?? $field['id'] ?? null;

            if (is_string($inputName)) {
                $inputNames[] = $inputName;
            }
        }

        return $inputNames;
    }
}
