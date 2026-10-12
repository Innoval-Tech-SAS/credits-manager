<?php

namespace App\Facturacion\Requests;

use App\Facturacion\Enums\TipoConceptoFacturacion;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearBorradorFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'credito_id' => [
                'required', 'integer',
                Rule::exists('creditos', 'id')->where(fn (Builder $consulta) => $consulta
                    ->where('empresa_id', $this->user()->empresa_id)
                    ->where('cliente_id', $this->input('cliente_id'))),
            ],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'fecha_vencimiento' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.tipo_concepto' => ['required', Rule::enum(TipoConceptoFacturacion::class)],
            'lineas.*.cuota_id' => [
                'nullable', 'integer',
                Rule::exists('cuotas', 'id')->where('credito_id', $this->input('credito_id')),
            ],
            'lineas.*.concepto' => ['required', 'string', 'max:60'],
            'lineas.*.descripcion' => ['required', 'string', 'max:255'],
            'lineas.*.valor' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
