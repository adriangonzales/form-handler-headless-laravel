<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Forms\BuildValidationRules;
use App\Models\Form;
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
     */
    public function rules(): array
    {
        /** @param Form **/
        $form = $this->route('form');

        return (new BuildValidationRules())($form);
    }
}
