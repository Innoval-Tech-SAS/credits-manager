<?php

namespace App\Importacion\Repositories;

use App\Core\Base\BaseRepository;
use App\Importacion\Enums\EstadoImportacion;
use App\Importacion\Models\Importacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ImportacionRepository extends BaseRepository
{
    public function __construct(Importacion $importacion)
    {
        parent::__construct($importacion);
    }

    public function paginarDeEmpresa(int $empresaId, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->consulta()
            ->where('empresa_id', $empresaId)
            ->with('usuario:id,nombre')
            ->orderByDesc('creado_en')
            ->orderByDesc('id')
            ->paginate($porPagina, ['*'], 'page', $pagina);
    }

    public function buscarDeEmpresaOFallar(int $id, int $empresaId): Importacion
    {
        return $this->consulta()->where('empresa_id', $empresaId)->findOrFail($id);
    }

    /** Cambia el estado solo si todavía está en `desde`: dos confirmaciones simultáneas no encolan la escritura dos veces. */
    public function transicionar(Importacion $importacion, array $desde, EstadoImportacion $hacia, array $datos = []): bool
    {
        return $this->consulta()
            ->whereKey($importacion->id)
            ->whereIn('estado', array_map(fn (EstadoImportacion $estado) => $estado->value, $desde))
            ->update([...$datos, 'estado' => $hacia->value, 'ultima_actualizacion' => now()]) === 1;
    }
}
