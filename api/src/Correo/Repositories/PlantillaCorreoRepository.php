<?php

namespace App\Correo\Repositories;

use App\Core\Base\BaseRepository;
use App\Correo\Enums\TipoCorreo;
use App\Correo\Models\PlantillaCorreo;
use Illuminate\Database\Eloquent\Collection;

class PlantillaCorreoRepository extends BaseRepository
{
    public function __construct(PlantillaCorreo $plantilla)
    {
        parent::__construct($plantilla);
    }

    /** Explícito por empresa: los jobs de envío no tienen sesión. */
    public function deEmpresa(int $id, int $empresaId): ?PlantillaCorreo
    {
        return $this->consulta()->where('empresa_id', $empresaId)->whereKey($id)->first();
    }

    /** @return Collection<int, PlantillaCorreo> */
    public function deEmpresaYTipo(int $empresaId, TipoCorreo $tipo): Collection
    {
        return $this->consulta()
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->orderBy('nombre')
            ->get();
    }

    /** Usado sin sesión al momento de enviar (recordatorio o reenvío de facturación). */
    public function predeterminadaDeEmpresa(int $empresaId, TipoCorreo $tipo): ?PlantillaCorreo
    {
        return $this->consulta()
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->where('es_predeterminada', true)
            ->first();
    }

    /** Hermanas del mismo empresa+tipo, bloqueadas para la transacción que cambia cuál es la predeterminada. */
    public function hermanasBloqueadas(int $empresaId, TipoCorreo $tipo): Collection
    {
        return $this->consulta()
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->lockForUpdate()
            ->get();
    }

    public function existeOtraDelTipo(int $empresaId, TipoCorreo $tipo, int $excluirId): bool
    {
        return $this->consulta()
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->whereKeyNot($excluirId)
            ->exists();
    }
}
