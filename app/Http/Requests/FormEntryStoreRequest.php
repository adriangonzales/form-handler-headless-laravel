<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Forms\BuildValidationRules;
use App\Models\Form;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FormEntryStoreRequest extends FormRequest
{
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
        $form = $this->route('form');

        if (! $form instanceof Form) {
            abort(404);
        }

        return (new BuildValidationRules)($form);
    }
}
