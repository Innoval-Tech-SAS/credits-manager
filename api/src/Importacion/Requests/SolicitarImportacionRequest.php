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
        return [
            'tipo' => ['required', Rule::enum(TipoImportacion::class)],
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:'.((int) config('importaciones.tamano_maximo_mb') * 1024)],
            'crear_empleadores' => ['nullable', 'boolean'],
            'generar_asiento' => ['nullable', 'boolean'],
        ];
    }

    /** @return array{tipo: TipoImportacion, archivo: UploadedFile, crear_empleadores: bool, generar_asiento: bool} */
    public function datos(): array
    {
        return [
            'tipo' => TipoImportacion::from($this->input('tipo')),
            'archivo' => $this->file('archivo'),
            'crear_empleadores' => $this->boolean('crear_empleadores'),
            'generar_asiento' => $this->boolean('generar_asiento'),
        ];
    }
}
