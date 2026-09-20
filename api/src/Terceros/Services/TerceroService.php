<?php

namespace App\Terceros\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Terceros\Models\Tercero;
use App\Terceros\Repositories\OtroTerceroRepository;
use App\Terceros\Repositories\TerceroRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TerceroService
{
    public function __construct(
        protected TerceroRepository $terceros,
        protected OtroTerceroRepository $otrosTerceros,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(?string $busqueda, ?string $tipo, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->terceros->paginar($busqueda, $tipo, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    /** Crea un tercero genérico (no cliente, no proveedor) junto con su extensión "otro", reutilizando el tercero si ya existe. */
    public function crear(array $datos): Tercero
    {
        return DB::transaction(function () use ($datos) {
            $tercero = $this->terceros->obtenerOCrear($datos);

            $this->otrosTerceros->crear(['tercero_id' => $tercero->id, 'activo' => true]);

            $this->auditoria->registrarCreacion(
                AuditoriaConstants::MODULO_TERCEROS,
                "Tercero {$tercero->nombre} creado",
                $tercero,
                ['tipo' => 'otro', 'tercero_reutilizado' => ! $tercero->wasRecentlyCreated],
            );

            return $tercero->setAttribute('tercero_reutilizado', ! $tercero->wasRecentlyCreated);
        });
    }

    public function editar(Tercero $tercero, array $datos): Tercero
    {
        $anteriores = $this->auditoria->instantanea($tercero);
        $tercero = $this->terceros->actualizar($tercero, $datos);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_TERCEROS, "Tercero {$tercero->nombre} modificado", $tercero, $anteriores);

        return $tercero;
    }
}
