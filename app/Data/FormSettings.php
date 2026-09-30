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
        #[Max(2000)]
        public ?string $message = null,
        public bool $honeypot_enabled = false,
        public ?string $honeypot_name = null,
        // TODO: CAPTCHA type (none, recaptcha, hcaptcha)
        // TODO: CAPTCHA secret key
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
            'honeypot_name' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    /**
     * Determine whether the submitted input trips the honeypot: it is enabled and its field has a value.
     *
     * @param  array<string, mixed>  $input
     */
    public function honeypotTripped(array $input): bool
    {
        if (! $this->honeypot_enabled || $this->honeypot_name === null) {
            return false;
        }

        $value = $input[$this->honeypot_name] ?? null;

        return ! in_array($value, [null, '', []], true);
    }

    /**
     * Determine whether a submission with the given Referer header is allowed by the domains setting.
     *
     * With no domains set every submission is allowed. Otherwise the referer's host must equal a domain,
     * or be a subdomain of a `*.` wildcard domain (which does not match the bare domain itself).
     */
    public function allowsReferer(?string $referer): bool
    {
        if ($this->domains === null || $this->domains === []) {
            return true;
        }

        $host = parse_url((string) $referer, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        foreach ($this->domains as $domain) {
            $domain = strtolower($domain);

            if (str_starts_with($domain, '*.')) {
                if (str_ends_with($host, substr($domain, 1))) {
                    return true;
                }
            } elseif ($host === $domain) {
                return true;
            }
        }

        return false;
    }
}
