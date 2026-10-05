<?php

namespace App\Metas\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditarMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'valor_objetivo' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
        ];
    }
}
