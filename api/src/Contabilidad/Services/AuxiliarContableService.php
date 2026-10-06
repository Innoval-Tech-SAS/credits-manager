<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Exportaciones\FuenteAuxiliarContable;
use App\Contabilidad\Repositories\AsientoContableRepository;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use App\Core\Dinero\Dinero;
use App\Exportacion\Enums\FormatoExportacion;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Services\ExportacionService;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Collection;

/**
 * Auxiliar por cuenta (los movimientos de una cuenta, con su tercero) o por tercero (sus movimientos, agrupados por
 * cuenta). Ambos salen del mismo cálculo: saldo inicial + movimientos del rango = saldo final, con el signo de la
 * naturaleza de cada cuenta. Solo cuenta asientos de comprobantes contabilizados.
 */
class AuxiliarContableService
{
    public function __construct(
        protected AsientoContableRepository $asientos,
        protected PlanCuentaRepository $cuentas,
        protected ExportacionService $exportaciones,
    ) {}

    public function solicitarExportacion(Usuario $usuario, string $desde, string $hasta, ?int $cuentaId, ?int $terceroId, FormatoExportacion $formato): Exportacion
    {
        return $this->exportaciones->solicitar(new FuenteAuxiliarContable((int) $usuario->empresa_id, $desde, $hasta, $cuentaId, $terceroId), $formato, $usuario);
    }

    /** @return array{grupos: list<array<string, mixed>>, total_debito: Dinero, total_credito: Dinero} */
    public function auxiliar(int $empresaId, string $desde, string $hasta, ?int $cuentaId = null, ?int $terceroId = null): array
    {
        $iniciales = $this->asientos->saldosAuxiliarAntesDe($empresaId, $desde, $cuentaId, $terceroId)->keyBy('cuenta_id');
        $movimientos = $this->asientos->movimientosAuxiliar($empresaId, $desde, $hasta, $cuentaId, $terceroId)->groupBy('cuenta_id');

        $grupos = $iniciales->keys()
            ->merge($movimientos->keys())
            ->unique()
            ->map(fn ($id) => $this->grupo((int) $id, $iniciales->get($id), $movimientos->get($id, collect())))
            ->sortBy('cuenta.codigo')
            ->values();

        // Una cuenta consultada sin movimientos debe aparecer igual, con saldo cero, para que el auxiliar no parezca incompleto.
        if ($cuentaId !== null && $grupos->isEmpty()) {
            $cuenta = $this->cuentas->buscarDeEmpresaOFallar($empresaId, $cuentaId);
            $grupos = collect([$this->grupo($cuenta->id, null, collect(), $cuenta->codigo, $cuenta->nombre, $cuenta->naturaleza->value)]);
        }

        return [
            'grupos' => $grupos->all(),
            'total_debito' => Dinero::sumarVarios(...$grupos->pluck('total_debito')->all()),
            'total_credito' => Dinero::sumarVarios(...$grupos->pluck('total_credito')->all()),
        ];
    }

    private function grupo(int $cuentaId, ?object $inicial, Collection $movimientos, ?string $codigo = null, ?string $nombre = null, ?string $naturaleza = null): array
    {
        $origen = $inicial ?? $movimientos->first();
        $naturaleza = NaturalezaCuenta::from($naturaleza ?? $origen->cuenta_naturaleza);

        $saldoInicial = $inicial
            ? $this->saldoNatural($naturaleza, Dinero::desdeCentavos((int) $inicial->debitos), Dinero::desdeCentavos((int) $inicial->creditos))
            : Dinero::cero();

        $saldo = $saldoInicial;
        $totalDebito = Dinero::cero();
        $totalCredito = Dinero::cero();
        $filas = [];

        foreach ($movimientos as $movimiento) {
            $saldo = $naturaleza === NaturalezaCuenta::Debito
                ? $saldo->sumar($movimiento->debito)->restar($movimiento->credito)
                : $saldo->sumar($movimiento->credito)->restar($movimiento->debito);

            $totalDebito = $totalDebito->sumar($movimiento->debito);
            $totalCredito = $totalCredito->sumar($movimiento->credito);

            $filas[] = [
                'fecha' => $movimiento->fecha,
                'comprobante' => "{$movimiento->tipo}-{$movimiento->consecutivo}",
                'descripcion' => $movimiento->descripcion,
                'tercero' => $movimiento->tercero,
                'debito' => $movimiento->debito,
                'credito' => $movimiento->credito,
                'saldo' => $saldo,
            ];
        }

        return [
            'cuenta' => [
                'id' => $cuentaId,
                'codigo' => $codigo ?? $origen->cuenta_codigo,
                'nombre' => $nombre ?? $origen->cuenta_nombre,
                'naturaleza' => $naturaleza->value,
            ],
            'saldo_inicial' => $saldoInicial,
            'total_debito' => $totalDebito,
            'total_credito' => $totalCredito,
            'saldo_final' => $saldo,
            'movimientos' => $filas,
        ];
    }

    private function saldoNatural(NaturalezaCuenta $naturaleza, Dinero $debitos, Dinero $creditos): Dinero
    {
        return $naturaleza === NaturalezaCuenta::Debito ? $debitos->restar($creditos) : $creditos->restar($debitos);
    }
}
