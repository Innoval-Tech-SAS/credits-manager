<?php

namespace App\Contabilidad\Requests;

use App\Contabilidad\Rules\CuentaDeMovimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida solo la FORMA del comprobante. El cuadre débito=crédito es una regla de
 * negocio y vive en `ComprobanteService::crearComprobante()` (ValidationException),
 * no aquí — igual que en el legado, donde el cuadre se valida en el motor, no en el
 * formulario.
 */
class CrearComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_comprobante_id' => [
                'required',
                Rule::exists('tipos_comprobante', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
            'fecha' => ['required', 'date'],
            'concepto' => ['required', 'string', 'max:255'],
            'documento_soporte' => ['nullable', 'string', 'max:150'],
            'consecutivo' => ['nullable', 'integer', 'min:1'],
            'tercero_id' => [
                'nullable',
                Rule::exists('terceros', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.cuenta_id' => ['required', new CuentaDeMovimiento($this->user()->empresa_id)],
            'lineas.*.centro_costo_id' => [
                'nullable',
                Rule::exists('centros_costo', 'id')->where('empresa_id', $this->user()->empresa_id)->where('activo', true)->whereNull('borrado_en'),
            ],
            'lineas.*.tercero_id' => [
                'nullable',
                Rule::exists('terceros', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
            'lineas.*.documento_cruce' => ['nullable', 'string', 'max:60'],
            'lineas.*.fecha_vencimiento' => ['nullable', 'date'],
            'lineas.*.debito' => ['nullable', 'numeric', 'min:0'],
            'lineas.*.credito' => ['nullable', 'numeric', 'min:0'],
            'lineas.*.descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
