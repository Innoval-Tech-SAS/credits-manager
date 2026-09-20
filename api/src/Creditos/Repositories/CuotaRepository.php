<?php

namespace App\Creditos\Repositories;

use App\Core\Base\BaseRepository;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Models\Cuota;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CuotaRepository extends BaseRepository
{
    public function __construct(Cuota $cuota)
    {
        parent::__construct($cuota);
    }

    public function pendientesOrdenadasParaActualizar(int $creditoId): Collection
    {
        return $this->consulta()
            ->where('credito_id', $creditoId)
            ->whereIn('estado', [EstadoCuota::Pendiente, EstadoCuota::Vencida])
            ->orderBy('numero_cuota')
            ->lockForUpdate()
            ->get();
    }

    public function todasOrdenadasParaActualizar(int $creditoId): Collection
    {
        return $this->consulta()
            ->where('credito_id', $creditoId)
            ->orderBy('numero_cuota')
            ->lockForUpdate()
            ->get();
    }

    public function eliminarTodasDeCredito(int $creditoId): void
    {
        $this->consulta()->where('credito_id', $creditoId)->delete();
    }

    public function todasPorCredito(int $creditoId): Collection
    {
        return $this->consulta()
            ->where('credito_id', $creditoId)
            ->orderBy('numero_cuota')
            ->get();
    }

    public function contarNoPagadas(int $creditoId): int
    {
        return $this->consulta()
            ->where('credito_id', $creditoId)
            ->where('estado', '!=', EstadoCuota::Pagada)
            ->count();
    }

    public function crearVarias(int $creditoId, array $filas): void
    {
        $ahora = now();

        Cuota::insert(array_map(fn (array $fila) => [
            ...$fila,
            'valor' => $fila['valor']->centavos(),
            'valor_interes' => $fila['valor_interes']->centavos(),
            'valor_capital' => $fila['valor_capital']->centavos(),
            'saldo_capital' => $fila['saldo_capital']->centavos(),
            'credito_id' => $creditoId,
            'estado' => EstadoCuota::Pendiente->value,
            'creado_en' => $ahora,
            'ultima_actualizacion' => $ahora,
        ], $filas));
    }

    /**
     * `cuotas` no tiene `empresa_id` propio (es línea de detalle de `creditos`, igual que
     * `asientos_contables` respecto a `comprobantes`): el aislamiento por empresa se hace
     * vía `whereHas('credito')`, que hereda el `EmpresaScope` de `Credito`. Sin este filtro,
     * esta actualización corría sobre las cuotas de TODAS las empresas del sistema.
     */
    public function marcarVencidas(): Collection
    {
        return DB::transaction(function () {
            // lockForUpdate: dos peticiones simultáneas no marcan (ni reportan) las mismas cuotas dos veces.
            $cuotas = $this->consulta()
                ->where('estado', EstadoCuota::Pendiente)
                ->where('fecha_vencimiento', '<', now()->toDateString())
                ->whereHas('credito')
                ->with('credito:id,empresa_id')
                ->lockForUpdate()
                ->get(['id', 'credito_id']);

            foreach (array_chunk($cuotas->modelKeys(), 1000) as $lote) {
                $this->consulta()->whereIn('id', $lote)->update(['estado' => EstadoCuota::Vencida]);
            }

            return $cuotas;
        });
    }

    /** Centavos enteros — `SUM()` de MySQL sobre columnas `bigint` es exacto. */
    public function saldoPendiente(int $creditoId): int
    {
        $saldo = $this->consulta()
            ->where('credito_id', $creditoId)
            ->select(DB::raw('SUM(valor - valor_pagado) as saldo'))
            ->value('saldo');

        return (int) ($saldo ?? 0);
    }

    public function contarVencidas(int $creditoId): int
    {
        return $this->consulta()
            ->where('credito_id', $creditoId)
            ->where('estado', EstadoCuota::Vencida)
            ->count();
    }

    public function primeraFechaVencida(int $creditoId): ?string
    {
        return $this->consulta()
            ->where('credito_id', $creditoId)
            ->where('estado', EstadoCuota::Vencida)
            ->min('fecha_vencimiento');
    }

    /** @return array<int, array{saldo: int, vencidas: int, primera_fecha_vencida: ?string}> `saldo` en centavos enteros. */
    public function resumenPorCreditos(array $creditoIds): array
    {
        return $this->consulta()
            ->whereIn('credito_id', $creditoIds)
            ->selectRaw('
                credito_id,
                SUM(valor - valor_pagado) as saldo,
                SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as vencidas,
                MIN(CASE WHEN estado = ? THEN fecha_vencimiento END) as primera_fecha_vencida
            ', [EstadoCuota::Vencida->value, EstadoCuota::Vencida->value])
            ->groupBy('credito_id')
            ->get()
            ->keyBy('credito_id')
            ->map(fn ($fila) => [
                'saldo' => (int) $fila->saldo,
                'vencidas' => (int) $fila->vencidas,
                'primera_fecha_vencida' => $fila->primera_fecha_vencida,
            ])
            ->all();
    }

    /**
     * Todas las cuotas (sin importar `estado`) de créditos desembolsados o ya pagados cuyo
     * vencimiento cae dentro de `[$desde, $hasta]` — a diferencia del resto del
     * calendario/dashboard, esta consulta necesita también las ya pagadas para pintarlas en
     * verde en el mes. Incluye `Pagado` porque un crédito liquidado en su última cuota pasa
     * de `Desembolsado` a `Pagado` en el mismo instante en que esa cuota se marca pagada.
     */
    public function delMesDeCreditosActivos(string $desde, string $hasta): Collection
    {
        return $this->consulta()
            ->whereBetween('fecha_vencimiento', [$desde, $hasta])
            ->whereHas('credito', fn ($consultaCredito) => $consultaCredito->whereIn('estado', [EstadoCredito::Desembolsado, EstadoCredito::Pagado]))
            ->with(['credito.cliente.tercero'])
            ->get()
            ->sortBy(fn (Cuota $cuota) => $cuota->fecha_vencimiento->toDateString())
            ->values();
    }

    public function venceHoyDeCreditosActivos(): Collection
    {
        return $this->consulta()
            ->where('fecha_vencimiento', now()->toDateString())
            ->where('estado', '!=', EstadoCuota::Pagada)
            ->whereHas('credito', fn ($consultaCredito) => $consultaCredito->where('estado', EstadoCredito::Desembolsado))
            ->with(['credito.cliente.tercero'])
            ->get()
            ->sortBy(fn (Cuota $cuota) => $cuota->credito->cliente->tercero->nombre)
            ->values();
    }

    public function porEmpleador(int $empleadorId): Collection
    {
        return $this->consulta()
            ->whereHas('credito', function ($consultaCredito) use ($empleadorId) {
                $consultaCredito->where('estado', EstadoCredito::Desembolsado)
                    ->whereHas('cliente', fn ($consultaCliente) => $consultaCliente->where('empleador_id', $empleadorId));
            })
            ->with(['credito.cliente.tercero'])
            ->get()
            ->sortBy(fn (Cuota $cuota) => $cuota->credito->cliente->tercero->nombre.'|'.$cuota->fecha_vencimiento->format('Y-m-d'))
            ->values();
    }

    public function cerrarPendientesComoOrigen(int $creditoId): void
    {
        $this->consulta()
            ->where('credito_id', $creditoId)
            ->where('estado', '!=', EstadoCuota::Pagada)
            ->update([
                'estado' => EstadoCuota::Pagada,
                'valor_pagado' => DB::raw('valor'),
            ]);
    }
}
