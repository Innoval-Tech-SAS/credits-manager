<?php

namespace App\Auditoria\Services;

use App\Auditoria\Data\FiltrosBitacora;
use App\Auditoria\Data\Visibilidad;
use App\Auditoria\Exportaciones\FuenteBitacoraAuditoria;
use App\Auditoria\Repositories\BitacoraAuditoriaRepository;
use App\Exportacion\Enums\FormatoExportacion;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Services\ExportacionService;
use App\Usuarios\Models\Usuario;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class BitacoraAuditoriaService
{
    public function __construct(
        protected BitacoraAuditoriaRepository $bitacora,
        protected AccesoAuditoria $acceso,
        protected ExportacionService $exportaciones,
    ) {}

    public function listar(Usuario $usuario, array $validados, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $this->acceso->exigir($usuario, 'listar');

        return $this->bitacora->paginarVisibles(
            Visibilidad::desdeUsuario($usuario),
            $this->filtros($usuario, $validados),
            max(1, min(100, $porPagina)),
            max(1, $pagina),
        );
    }

    /** El permiso (`auditoria.exportar_bitacora` o superadmin) lo exige `ExportacionService::solicitar` vía la fuente. */
    public function solicitarExportacion(Usuario $usuario, array $validados): Exportacion
    {
        $fuente = new FuenteBitacoraAuditoria(Visibilidad::desdeUsuario($usuario), $this->filtros($usuario, $validados), $this->bitacora->ultimoId());

        return $this->exportaciones->solicitar($fuente, FormatoExportacion::from($validados['formato']), $usuario);
    }

    /** Solo el superadmin puede filtrar por empresa; para el resto ese filtro se descarta. */
    private function filtros(Usuario $usuario, array $validados): FiltrosBitacora
    {
        return FiltrosBitacora::desdeValidados($usuario->es_superadmin ? $validados : Arr::except($validados, 'empresa_id'));
    }
}
