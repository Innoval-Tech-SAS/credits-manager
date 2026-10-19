<?php

namespace App\Importacion\Repositories;

use App\Clientes\Models\Cliente;
use App\Contabilidad\Models\AsientoContable;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Models\SaldoApertura;
use App\Contabilidad\Support\EfectoContable;
use App\Creditos\Models\Credito;
use App\Creditos\Models\Cuota;
use App\Empleadores\Models\Empleador;
use App\Gastos\Models\ActividadEconomica;
use App\Importacion\Lectura\ConvertidorValor;
use App\Pagos\Models\Pago;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;

/**
 * Búsquedas de referencia para los procesadores. Corren en el worker sin sesión: cada una recibe el `empresa_id`
 * explícito y lo filtra, sin depender del scope de Eloquent (que no filtra sin usuario autenticado).
 */
class DatosImportacionRepository
{
    /** @param list<string> $cedulas @return array<string, int> cédula → id de cliente */
    public function clientesPorCedulas(int $empresaId, array $cedulas): array
    {
        if ($cedulas === []) {
            return [];
        }

        return Cliente::query()
            ->join('terceros', 'terceros.id', '=', 'clientes.tercero_id')
            ->where('clientes.empresa_id', $empresaId)
            ->where('terceros.empresa_id', $empresaId)
            ->whereNull('terceros.borrado_en')
            ->whereIn('terceros.numero_documento', $cedulas)
            ->select(['clientes.id as id', 'terceros.numero_documento as cedula'])
            ->pluck('id', 'cedula')
            ->all();
    }

    /** @param list<string> $codigos @return array<string, int> */
    public function creditosPorCodigos(int $empresaId, array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }

        return Credito::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('codigo', $codigos)
            ->pluck('id', 'codigo')
            ->all();
    }

    /** @param list<int> $clienteIds @return array<int, int> id de cliente → tercero_id */
    public function tercerosPorClientes(int $empresaId, array $clienteIds): array
    {
        if ($clienteIds === []) {
            return [];
        }

        return Cliente::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $clienteIds)
            ->pluck('tercero_id', 'id')
            ->all();
    }

    /** @param list<int> $creditoIds @return array<int, int> id de crédito → tercero_id de su cliente */
    public function tercerosPorCreditos(int $empresaId, array $creditoIds): array
    {
        if ($creditoIds === []) {
            return [];
        }

        return Credito::query()
            ->join('clientes', 'clientes.id', '=', 'creditos.cliente_id')
            ->where('creditos.empresa_id', $empresaId)
            ->where('clientes.empresa_id', $empresaId)
            ->whereIn('creditos.id', $creditoIds)
            ->pluck('clientes.tercero_id', 'creditos.id')
            ->all();
    }

    /** @param list<string> $codigos @return array<string, int> */
    public function cuentasPorCodigos(int $empresaId, array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }

        return PlanCuenta::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('codigo', $codigos)
            ->pluck('id', 'codigo')
            ->all();
    }

    /** @param list<string> $codigos @return array<string, PlanCuenta> código → cuenta, con los campos que deciden si exige tercero (ver `PlanCuenta::esCarteraDeTerceros()`) */
    public function cuentasConDatosPorCodigos(int $empresaId, array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }

        return PlanCuenta::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('codigo', $codigos)
            ->get(['id', 'codigo', 'exige_tercero', 'exige_documento_cruce'])
            ->keyBy('codigo')
            ->all();
    }

    /** @param list<string> $codigos @return array<string, ActividadEconomica> código CIIU → actividad económica */
    public function actividadesEconomicasPorCodigos(int $empresaId, array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }

        return ActividadEconomica::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('codigo', $codigos)
            ->get()
            ->keyBy('codigo')
            ->all();
    }

    /** @return array<string, int> nombre normalizado → id */
    public function empleadoresPorNombre(int $empresaId): array
    {
        return Empleador::query()
            ->where('empresa_id', $empresaId)
            ->get(['id', 'nombre'])
            ->mapWithKeys(fn (Empleador $empleador) => [ConvertidorValor::nombreNormalizado($empleador->nombre) => $empleador->id])
            ->all();
    }

    /** @return array<string, int> nombre normalizado → id */
    public function usuariosPorNombre(int $empresaId): array
    {
        return Usuario::query()
            ->where('empresa_id', $empresaId)
            ->get(['id', 'nombre'])
            ->mapWithKeys(fn (Usuario $usuario) => [ConvertidorValor::nombreNormalizado((string) $usuario->nombre) => $usuario->id])
            ->all();
    }

    /** @return Collection<int, Cuota> */
    public function cuotasDelCredito(int $creditoId): Collection
    {
        return Cuota::query()->where('credito_id', $creditoId)->orderBy('numero_cuota')->get();
    }

    public function creditoTienePagos(int $creditoId): bool
    {
        return Pago::query()->where('credito_id', $creditoId)->exists();
    }

    public function saldoExiste(int $empresaId, int $cuentaId, string $fechaCorte): bool
    {
        return SaldoApertura::query()
            ->where('empresa_id', $empresaId)
            ->where('cuenta_id', $cuentaId)
            ->whereDate('fecha_corte', $fechaCorte)
            ->exists();
    }

    public function ultimaFechaCorteApertura(int $empresaId): ?string
    {
        $fecha = SaldoApertura::query()->where('empresa_id', $empresaId)->max('fecha_corte');

        return $fecha === null ? null : substr((string) $fecha, 0, 10);
    }

    /**
     * Fecha del primer asiento con efecto contable de cada cuenta. Un saldo de apertura con corte en o después de esa
     * fecha contaría dos veces lo ya contabilizado.
     *
     * @param  list<int>  $cuentaIds
     * @return array<int, string> id de cuenta → fecha (Y-m-d)
     */
    public function primerAsientoPorCuenta(int $empresaId, array $cuentaIds): array
    {
        if ($cuentaIds === []) {
            return [];
        }

        return AsientoContable::query()
            ->join('comprobantes', 'comprobantes.id', '=', 'asientos_contables.comprobante_id')
            ->where('comprobantes.empresa_id', $empresaId)
            ->whereNull('comprobantes.borrado_en')
            ->whereIn('asientos_contables.cuenta_id', $cuentaIds)
            ->where(EfectoContable::condicion())
            ->groupBy('asientos_contables.cuenta_id')
            ->selectRaw('asientos_contables.cuenta_id, MIN(comprobantes.fecha) as primera_fecha')
            ->pluck('primera_fecha', 'asientos_contables.cuenta_id')
            ->map(fn ($fecha) => substr((string) $fecha, 0, 10))
            ->all();
    }
}
