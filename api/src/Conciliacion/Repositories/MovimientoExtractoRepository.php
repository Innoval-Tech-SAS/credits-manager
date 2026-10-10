<?php

namespace App\Conciliacion\Repositories;

use App\Conciliacion\Enums\EstadoMovimientoExtracto;
use App\Conciliacion\Lectura\Data\MovimientoLeido;
use App\Conciliacion\Models\MovimientoExtracto;
use App\Core\Base\BaseRepository;
use Illuminate\Support\Facades\DB;

class MovimientoExtractoRepository extends BaseRepository
{
    public function __construct(MovimientoExtracto $movimientoExtracto)
    {
        parent::__construct($movimientoExtracto);
    }

    /**
     * Inserción masiva (un extracto trae cientos de filas): una sola consulta en vez de un `crear()` por fila.
     *
     * @param  list<MovimientoLeido>  $movimientos
     */
    public function insertarVarios(int $empresaId, int $extractoBancarioId, array $movimientos): void
    {
        if ($movimientos === []) {
            return;
        }

        $ahora = now();

        DB::table('movimientos_extracto')->insert(array_map(fn (MovimientoLeido $movimiento) => [
            'empresa_id' => $empresaId,
            'extracto_bancario_id' => $extractoBancarioId,
            'fecha' => $movimiento->fecha,
            'descripcion' => $movimiento->descripcion,
            'sucursal' => $movimiento->sucursal,
            'documento' => $movimiento->documento,
            'valor' => $movimiento->valor->centavos(),
            'saldo' => $movimiento->saldo->centavos(),
            'orden' => $movimiento->orden,
            'estado' => EstadoMovimientoExtracto::Pendiente->value,
            'creado_en' => $ahora,
            'ultima_actualizacion' => $ahora,
        ], $movimientos));
    }

    public function contarPendientes(int $extractoBancarioId): int
    {
        return $this->consulta()
            ->where('extracto_bancario_id', $extractoBancarioId)
            ->where('estado', EstadoMovimientoExtracto::Pendiente->value)
            ->count();
    }
}
