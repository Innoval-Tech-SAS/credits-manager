<?php

namespace App\Importacion\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecidirImportacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'crear_empleadores' => ['nullable', 'boolean', 'required_without:generar_asiento'],
            'generar_asiento' => ['nullable', 'boolean', 'required_without:crear_empleadores'],
        ];
    }

    /** @return array{crear_empleadores: ?bool, generar_asiento: ?bool} */
    public function decisiones(): array
    {
        return [
            'crear_empleadores' => $this->has('crear_empleadores') ? $this->boolean('crear_empleadores') : null,
            'generar_asiento' => $this->has('generar_asiento') ? $this->boolean('generar_asiento') : null,
        ];
    }
}
