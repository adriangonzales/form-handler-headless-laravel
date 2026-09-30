<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait FormNotificationValueRules
{
    /**
     * An E.164 phone number: "+", a country code that does not start with 0, and at most 15 digits.
     */
    public const string E164_PATTERN = '/^\+[1-9]\d{1,14}$/';

    /**
     * Get the validation rules for a recipient's value, which depend on its type: an email address for
     * "email", an E.164 phone number for "sms".
     *
     * @return list<ValidationRule|string>
     */
    protected function valueRules(): array
    {
        return match ($this->input('type')) {
            'email' => ['required', 'string', 'max:255', 'email'],
            'sms' => ['required', 'string', 'regex:'.self::E164_PATTERN],
            default => ['required', 'string', 'max:255'],
        };
    }
}
