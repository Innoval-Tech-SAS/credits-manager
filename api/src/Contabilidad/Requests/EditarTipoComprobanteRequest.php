<?php

namespace App\Contabilidad\Requests;

use App\Contabilidad\Enums\ReinicioConsecutivo;
use App\Contabilidad\Enums\TipoNumeracionComprobante;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditarTipoComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => [
                'sometimes', 'string', 'max:10',
                Rule::unique('tipos_comprobante', 'codigo')
                    ->where('empresa_id', $this->user()->empresa_id)
                    ->ignore($this->route('tipoComprobante')),
            ],
            'nombre' => ['sometimes', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
            'consecutivo_inicial' => ['sometimes', 'integer', 'min:1'],
            'consecutivo_final' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'tipo_numeracion' => ['sometimes', Rule::enum(TipoNumeracionComprobante::class)],
            'reinicio_periodo' => ['sometimes', Rule::enum(ReinicioConsecutivo::class)],
        ];
    }
}
