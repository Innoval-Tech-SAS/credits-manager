<?php

namespace App\Egresos\Requests;

use App\Egresos\Enums\MedioPagoEgreso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearEgresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** El soporte va en el mismo multipart que el resto del egreso, así que `aplicaciones` llega como JSON-string: se decodifica antes de validar, igual que un array normal cuando el body es JSON puro. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('aplicaciones'))) {
            $this->merge(['aplicaciones' => json_decode((string) $this->input('aplicaciones'), true) ?? []]);
        }
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'tercero_id' => ['required', Rule::exists('terceros', 'id')->where('empresa_id', $empresaId)],
            'tipo_comprobante_id' => ['required', Rule::exists('tipos_comprobante', 'id')->where('empresa_id', $empresaId)],
            'fecha' => ['required', 'date'],
            'medio_pago' => ['required', Rule::enum(MedioPagoEgreso::class)],
            'cuenta_caja_id' => ['required', Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId)],
            'concepto' => ['required', 'string', 'max:255'],
            'soporte' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:5120'],
            'aplicaciones' => ['required', 'array', 'min:1'],
            'aplicaciones.*.documento_tercero_id' => [
                'required',
                'integer',
                Rule::exists('documentos_tercero', 'id')->where('empresa_id', $empresaId),
            ],
            'aplicaciones.*.valor' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
