<?php

namespace App\ObligacionesTributarias\Requests;

use App\ObligacionesTributarias\Enums\EstadoObligacionTributaria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarObligacionesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'periodo' => ['sometimes', 'string', 'max:20'],
            'mes' => ['sometimes', 'date_format:Y-m'],
            'tipo_obligacion_id' => ['sometimes', Rule::exists('tipos_obligacion_tributaria', 'id')->where('empresa_id', $empresaId)],
            'estado' => ['sometimes', Rule::enum(EstadoObligacionTributaria::class)],
            'responsable_id' => ['sometimes', Rule::exists('usuarios', 'id')->where('empresa_id', $empresaId)],
        ];
    }
}
