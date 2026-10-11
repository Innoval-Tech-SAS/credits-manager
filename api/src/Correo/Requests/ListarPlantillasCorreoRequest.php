<?php

namespace App\Correo\Requests;

use App\Correo\Enums\TipoCorreo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarPlantillasCorreoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoCorreo::class)],
        ];
    }
}
