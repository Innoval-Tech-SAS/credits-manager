<?php

namespace App\Exportacion\Repositories;

use App\Core\Base\BaseRepository;
use App\Core\Scopes\EmpresaScope;
use App\Exportacion\Enums\EstadoExportacion;
use App\Exportacion\Models\Exportacion;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

class ExportacionRepository extends BaseRepository
{
    public function __construct(Exportacion $exportacion)
    {
        parent::__construct($exportacion);
    }

    public function paginarDelUsuario(int $usuarioId, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->consulta()
            ->where('usuario_id', $usuarioId)
            ->orderByDesc('id')
            ->paginate($porPagina, page: $pagina);
    }

    public function buscarDelUsuarioOFallar(int $id, int $usuarioId): Exportacion
    {
        return $this->consulta()->where('usuario_id', $usuarioId)->findOrFail($id);
    }

    /**
     * Limpieza programable (comando artisan, sin sesión): exportaciones terminadas y más antiguas que el corte.
     * Excepción declarada al scope de empresa: recorre todas las empresas, a propósito.
     *
     * @return LazyCollection<int, Exportacion>
     */
    public function vencidas(CarbonInterface $antesDe): LazyCollection
    {
        return Exportacion::withoutGlobalScope(EmpresaScope::class)
            ->whereIn('estado', [EstadoExportacion::Completada->value, EstadoExportacion::Fallida->value])
            ->where('creado_en', '<', $antesDe)
            ->lazyById();
    }

    /**
     * Único acceso sin sesión (worker de la cola): no hay Auth, así que el alcance lo fija el
     * `empresa_id` explícito del job. Excepción declarada al scope de empresa.
     */
    public function buscarParaProcesar(int $id, ?int $empresaId): Exportacion
    {
        return Exportacion::withoutGlobalScope(EmpresaScope::class)
            ->with([
                'usuario' => fn ($consulta) => $consulta->withoutGlobalScope(EmpresaScope::class),
                'empresa',
            ])
            ->where('empresa_id', $empresaId)
            ->findOrFail($id);
    }
}
