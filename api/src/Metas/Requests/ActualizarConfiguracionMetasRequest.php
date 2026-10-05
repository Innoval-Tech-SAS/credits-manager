<?php

namespace App\Metas\Requests;

use App\Metas\Enums\BaseRecaudo;
use App\Metas\Enums\DiaDeCierre;
use App\Metas\Enums\ModoColocacionRefinanciaciones;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Todos los parámetros son obligatorios: el formulario siempre envía la configuración completa. */
class ActualizarConfiguracionMetasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tolerancia_riesgo_puntos' => ['required', 'integer', 'between:0,100'],
            'cumplimiento_parcial_minimo' => ['required', 'integer', 'between:0,100'],
            'alerta_alcanzada_activa' => ['required', 'boolean'],
            'umbral_alcanzada' => ['required', 'integer', 'between:1,200'],
            'modo_colocacion_refinanciaciones' => ['required', Rule::enum(ModoColocacionRefinanciaciones::class)],
            'base_recaudo' => ['required', Rule::enum(BaseRecaudo::class)],
            'distribucion_obligatoria_completa' => ['required', 'boolean'],
            'meta_consolidada_visible_asesor' => ['required', 'boolean'],
            'dia_de_cierre' => ['required', Rule::enum(DiaDeCierre::class)],
        ];
    }
}
