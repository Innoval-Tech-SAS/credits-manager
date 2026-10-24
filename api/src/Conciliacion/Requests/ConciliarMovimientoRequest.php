<?php

namespace App\Conciliacion\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class ConciliarMovimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // `asientos_contables` no tiene `empresa_id`: se valida la pertenencia con un join contra `comprobantes`,
            // igual que acota las consultas de candidatos (ver AsientoContableRepository).
            'asiento_contable_id' => ['required', 'integer', function (string $atributo, mixed $valor, Closure $fail) {
                $existe = DB::table('asientos_contables')
                    ->join('comprobantes', 'comprobantes.id', '=', 'asientos_contables.comprobante_id')
                    ->where('asientos_contables.id', $valor)
                    ->where('comprobantes.empresa_id', $this->user()->empresa_id)
                    ->exists();

                if (! $existe) {
                    $fail(__('validation.custom.conciliacion.asiento_no_pertenece'));
                }
            }],
        ];
    }
}
