<?php

namespace App\Importacion\Requests;

use App\Importacion\Enums\TipoImportacion;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class SolicitarImportacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoImportacion::class)],
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:'.((int) config('importaciones.tamano_maximo_mb') * 1024)],
            'crear_empleadores' => ['nullable', 'boolean'],
            'generar_asiento' => ['nullable', 'boolean'],
            // Sin regla fija de contabilización, el tipo de comprobante de la apertura lo elige quien sube el archivo.
            'tipo_comprobante_id' => [
                'nullable', 'integer',
                Rule::requiredIf(fn () => $this->input('tipo') === TipoImportacion::SaldosIniciales->value && $this->boolean('generar_asiento')),
                Rule::exists('tipos_comprobante', 'id')->where(fn (Builder $consulta) => $consulta->where('empresa_id', $this->user()->empresa_id)),
            ],
        ];
    }

    /** @return array{tipo: TipoImportacion, archivo: UploadedFile, crear_empleadores: bool, generar_asiento: bool, tipo_comprobante_id: ?int} */
    public function datos(): array
    {
        return [
            'tipo' => TipoImportacion::from($this->input('tipo')),
            'archivo' => $this->file('archivo'),
            'crear_empleadores' => $this->boolean('crear_empleadores'),
            'generar_asiento' => $this->boolean('generar_asiento'),
            'tipo_comprobante_id' => $this->input('tipo_comprobante_id') !== null ? (int) $this->input('tipo_comprobante_id') : null,
        ];
    }
}
