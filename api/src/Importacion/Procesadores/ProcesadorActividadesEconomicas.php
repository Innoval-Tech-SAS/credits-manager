<?php

namespace App\Importacion\Procesadores;

use App\Gastos\Services\ActividadEconomicaService;
use App\Importacion\Data\ContextoEjecucion;
use App\Importacion\Data\FilaRechazada;
use App\Importacion\Repositories\DatosImportacionRepository;

/** Catálogo CIIU por empresa: un código existente actualiza el nombre si cambió; uno nuevo se crea. Nunca duplica. */
final class ProcesadorActividadesEconomicas implements ProcesadorFila
{
    public function __construct(
        private readonly DatosImportacionRepository $datos,
        private readonly ActividadEconomicaService $actividades,
    ) {}

    public function precargar(array $valoresLote, ContextoEjecucion $ctx): void
    {
        $codigos = array_values(array_filter(array_column($valoresLote, 'codigo'), fn (string $codigo) => $codigo !== ''));

        $ctx->actividadesEconomicasPorCodigo = $this->datos->actividadesEconomicasPorCodigos($ctx->empresaId, $codigos);
    }

    public function procesar(array $valores, ContextoEjecucion $ctx): void
    {
        $codigo = $valores['codigo'];
        if ($codigo === '') {
            throw new FilaRechazada('El código CIIU es obligatorio.', 'codigo');
        }

        if (mb_strlen($codigo) > 20) {
            throw new FilaRechazada('El código CIIU supera los 20 caracteres.', 'codigo');
        }

        $nombre = $valores['nombre'];
        if ($nombre === '') {
            throw new FilaRechazada('La actividad económica es obligatoria.', 'nombre');
        }

        if (mb_strlen($nombre) > 150) {
            throw new FilaRechazada('La actividad económica supera los 150 caracteres.', 'nombre');
        }

        $existente = $ctx->actividadesEconomicasPorCodigo[$codigo] ?? null;

        if (! $ctx->escribir) {
            return;
        }

        if ($existente !== null) {
            if ($existente->nombre !== $nombre) {
                $this->actividades->editar($existente, ['nombre' => $nombre]);
            }

            return;
        }

        $ctx->actividadesEconomicasPorCodigo[$codigo] = $this->actividades->crear(['codigo' => $codigo, 'nombre' => $nombre, 'activo' => true]);
    }
}
