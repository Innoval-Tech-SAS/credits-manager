<?php

namespace App\Conciliacion\Repositories;

use App\Conciliacion\Enums\EstadoExtracto;
use App\Conciliacion\Models\ExtractoBancario;
use App\Core\Base\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class ExtractoBancarioRepository extends BaseRepository
{
    private const ESTADOS_VIGENTES = [EstadoExtracto::Cargando, EstadoExtracto::Cargado, EstadoExtracto::Cerrado];

    public function __construct(ExtractoBancario $extractoBancario)
    {
        parent::__construct($extractoBancario);
    }

    public function paraCuenta(int $cuentaBancariaId): Collection
    {
        return $this->consulta()
            ->where('cuenta_bancaria_id', $cuentaBancariaId)
            ->orderByDesc('periodo')
            ->get();
    }

    /** Dos extractos vigentes de la misma cuenta no pueden cubrir fechas en común (solape de rangos). */
    public function existeVigenteEnRango(int $cuentaBancariaId, string $fechaDesde, string $fechaHasta): bool
    {
        return $this->consulta()
            ->where('cuenta_bancaria_id', $cuentaBancariaId)
            ->where('fecha_desde', '<=', $fechaHasta)
            ->where('fecha_hasta', '>=', $fechaDesde)
            ->whereIn('estado', self::ESTADOS_VIGENTES)
            ->exists();
    }

    /** El job que la llama no tiene usuario autenticado: `EmpresaScope` no filtra sin sesión, así que acota aquí. */
    public function buscarPorIdYEmpresa(int $id, int $empresaId): ExtractoBancario
    {
        return $this->consulta()->where('empresa_id', $empresaId)->findOrFail($id);
    }
}
