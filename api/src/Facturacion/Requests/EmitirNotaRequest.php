<?php

namespace App\Facturacion\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nota crédito: cada línea corrige una línea de la factura (`linea_origen_id`) y toma su concepto.
 * Nota débito: cada línea trae su concepto y su descripción; puede vincularse a una línea de origen.
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

        return [
            'tipo' => ['required', Rule::in(['nota_credito', 'nota_debito'])],
            'motivo' => ['required', 'string', 'max:500'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.linea_origen_id' => [
                $esCredito ? 'required' : 'nullable',
                'integer',
                Rule::exists('lineas_documento_facturacion', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
            'lineas.*.concepto' => [$esCredito ? 'nullable' : 'required', 'string', 'max:60'],
            'lineas.*.descripcion' => [$esCredito ? 'nullable' : 'required', 'string', 'max:255'],
            'lineas.*.valor' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
