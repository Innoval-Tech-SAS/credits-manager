<?php

namespace App\Documentos\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubirArchivoAdjuntoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:5120'],
        ];
    }
}
