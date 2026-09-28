<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Timezone;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Data;

final class FormSettings extends Data
{
    /**
     * Create a new class instance.
     *
     * @param  list<string>|null  $domains
     */
    public function __construct(
        #[Url, Max(2048)]
        public ?string $redirect = null,
        #[Timezone]
        public ?string $timezone = null,
        public ?array $domains = [],
        // TODO: CAPTCHA type (none, recaptcha, hcaptcha)
        // TODO: CAPTCHA secret key
        // TODO: HoneyPot Enabled
        // TODO: HoneyPot Name
    ) {}

    /**
     * Additional validation rules that cannot be expressed as attributes.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'domains' => ['nullable', 'array', 'list'],
            'domains.*' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(\*\.)?([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/i'],
        ];
    }
}
