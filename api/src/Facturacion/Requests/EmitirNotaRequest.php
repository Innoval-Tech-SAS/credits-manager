<?php

namespace App\Facturacion\Requests;

use App\Facturacion\Enums\MotivoNotaCredito;
use App\Facturacion\Enums\MotivoNotaDebito;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nota crédito: cada línea corrige una línea de la factura (`linea_origen_id`) y toma su concepto.
 * Nota débito: cada línea trae su concepto y su descripción; puede vincularse a una línea de origen.
 * El motivo sale de una lista cerrada por tipo (`MotivoNotaCredito`/`MotivoNotaDebito`); «otros» exige detalle
 * en texto libre. La fecha de vencimiento y el soporte de mora solo tienen sentido en la nota débito.
 */
class EmitirNotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $esCredito = $this->input('tipo') === 'nota_credito';
        $motivos = $esCredito ? MotivoNotaCredito::cases() : MotivoNotaDebito::cases();
        $esMora = ! $esCredito && $this->input('motivo_codigo') === MotivoNotaDebito::Intereses->value;

        return [
            'tipo' => ['required', Rule::in(['nota_credito', 'nota_debito'])],
            'motivo_codigo' => ['required', Rule::in(array_column($motivos, 'value'))],
            'motivo_detalle' => [$this->esOtros() ? 'required' : 'nullable', 'string', 'max:500'],
            // El mayor valor de una nota débito debe quedar con vencimiento para entrar al cobro (7.2.2); la nota
            // crédito no lo usa (ver DocumentoFacturacionService::crearNota), así que ahí se queda opcional.
            'fecha_vencimiento' => [$esCredito ? 'nullable' : 'required', 'date'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.linea_origen_id' => [
                $esCredito ? 'required' : 'nullable',
                'integer',
                Rule::exists('lineas_documento_facturacion', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
            'lineas.*.concepto' => [$esCredito ? 'nullable' : 'required', 'string', 'max:60'],
            'lineas.*.descripcion' => [$esCredito ? 'nullable' : 'required', 'string', 'max:255'],
            'lineas.*.valor' => ['required', 'numeric', 'min:0.01'],
            'lineas.*.tasa_mora' => [$esMora ? 'required' : 'nullable', 'numeric', 'min:0'],
            'lineas.*.dias_mora' => [$esMora ? 'required' : 'nullable', 'integer', 'min:0'],
            'lineas.*.base_mora' => [$esMora ? 'required' : 'nullable', 'numeric', 'min:0.01'],
        ];
    }

    private function esOtros(): bool
    {
        return in_array($this->input('motivo_codigo'), [MotivoNotaCredito::Otros->value, MotivoNotaDebito::Otros->value], true);
    }
}
