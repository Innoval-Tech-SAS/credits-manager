<?php

namespace App\Importacion\Procesadores;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use App\Importacion\Data\ContextoEjecucion;
use App\Importacion\Data\FilaRechazada;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Formato anterior de plan de cuentas (encabezados codigo, nombre, naturaleza, tipo, nivel). */
final class ProcesadorPlanCuentas implements ProcesadorFila
{
    public function __construct(private readonly PlanCuentaRepository $cuentas) {}

    public function precargar(array $valoresLote, ContextoEjecucion $ctx): void {}

    public function procesar(array $valores, ContextoEjecucion $ctx): void
    {
        $valores = array_map(fn (string $valor) => $valor === '' ? null : $valor, $valores);

        $validador = Validator::make($valores, [
            'codigo' => ['required', 'string', 'max:20', Rule::unique('plan_cuentas', 'codigo')
                ->where('empresa_id', $ctx->empresaId)->whereNull('borrado_en')],
            'nombre' => ['required', 'string', 'max:150'],
            'naturaleza' => ['required', Rule::enum(NaturalezaCuenta::class)],
            'tipo' => ['required', Rule::enum(TipoCuenta::class)],
            'nivel' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validador->fails()) {
            $columna = array_key_first($validador->errors()->toArray());
            throw new FilaRechazada($validador->errors()->first(), is_string($columna) ? $columna : null);
        }

        if (! $ctx->primeraVez('cuenta|'.$valores['codigo'])) {
            throw new FilaRechazada('El código de cuenta está repetido en el archivo.', 'codigo');
        }

        if (! $ctx->escribir) {
            return;
        }

        $this->cuentas->crear([
            'codigo' => $valores['codigo'],
            'nombre' => $valores['nombre'],
            'naturaleza' => $valores['naturaleza'],
            'nivel' => $valores['nivel'] !== null ? (int) $valores['nivel'] : 1,
            'tipo' => $valores['tipo'],
            'permite_movimiento' => true,
        ]);
    }
}
