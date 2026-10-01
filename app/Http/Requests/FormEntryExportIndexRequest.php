<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FormEntryExportIndexRequest extends FormRequest
{
    /**
     * Largest page size a client may request with "per_page".
     */
    public const int MAX_PER_PAGE = 100;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** Results per page, from 1 to 100. Defaults to 15. */
            'per_page' => ['sometimes', 'integer', 'between:1,'.self::MAX_PER_PAGE],
        ];
    }

    /**
     * Get the requested page size, defaulting to 15.
     */
    public function perPage(): int
    {
        return $this->integer('per_page', 15);
    }
}
