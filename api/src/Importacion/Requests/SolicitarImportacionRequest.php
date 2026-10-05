<?php

namespace App\Importacion\Requests;

use App\Importacion\Enums\TipoImportacion;
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
        $empresaId = $this->user()->empresa_id;

        return [
            'tipo' => ['required', Rule::enum(TipoImportacion::class)],
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:'.((int) config('importaciones.tamano_maximo_mb') * 1024)],
            'crear_empleadores' => ['nullable', 'boolean'],
            'generar_asiento' => ['nullable', 'boolean'],
            'cuenta_contrapartida_id' => [
                Rule::requiredIf(fn () => $this->boolean('generar_asiento') && $this->input('tipo') === TipoImportacion::Creditos->value),
                'nullable', 'integer',
                Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId)->whereNull('borrado_en'),
            ],
        ];
    }

    /** @return array{tipo: TipoImportacion, archivo: UploadedFile, crear_empleadores: bool, generar_asiento: bool, cuenta_contrapartida_id: ?int} */
    public function datos(): array
    {
        return [
            'tipo' => TipoImportacion::from($this->input('tipo')),
            'archivo' => $this->file('archivo'),
            'crear_empleadores' => $this->boolean('crear_empleadores'),
            'generar_asiento' => $this->boolean('generar_asiento'),
            'cuenta_contrapartida_id' => $this->filled('cuenta_contrapartida_id') ? (int) $this->input('cuenta_contrapartida_id') : null,
        ];
    }
}
