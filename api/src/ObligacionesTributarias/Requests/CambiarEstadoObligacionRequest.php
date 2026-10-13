<?php

namespace App\ObligacionesTributarias\Requests;

use App\ObligacionesTributarias\Enums\EstadoObligacionTributaria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoObligacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoObligacionTributaria::class)],
        ];
    }
}
