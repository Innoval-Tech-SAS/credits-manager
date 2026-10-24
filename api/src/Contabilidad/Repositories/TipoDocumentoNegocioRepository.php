<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Enums\CodigoDocumentoNegocio;
use App\Contabilidad\Models\TipoDocumentoNegocio;
use App\Core\Base\BaseRepository;
use Illuminate\Support\Collection;

class TipoDocumentoNegocioRepository extends BaseRepository
{
    public function __construct(TipoDocumentoNegocio $tipo)
    {
        parent::__construct($tipo);
    }

    /** @return Collection<int, TipoDocumentoNegocio> las 6 filas de la empresa, ordenadas por código */
    public function paraEmpresa(int $empresaId): Collection
    {
        return $this->consulta()->where('empresa_id', $empresaId)->orderBy('codigo')->get();
    }

    public function buscarPorCodigo(int $empresaId, CodigoDocumentoNegocio $codigo): ?TipoDocumentoNegocio
    {
        return $this->consulta()->where('empresa_id', $empresaId)->where('codigo', $codigo)->first();
    }

    /** Siembra las 6 filas con los valores por defecto del enum: la usa el alta de empresa. */
    public function crearPorDefectoParaEmpresa(int $empresaId): void
    {
        foreach (CodigoDocumentoNegocio::cases() as $codigo) {
            $this->consulta()->firstOrCreate(
                ['empresa_id' => $empresaId, 'codigo' => $codigo],
                [
                    'nombre' => $codigo->nombrePorDefecto(),
                    'naturaleza' => $codigo->naturalezaPorDefecto(),
                    'crea_saldo' => $codigo->creaSaldoPorDefecto(),
                    'cruza_saldo' => $codigo->cruzaSaldoPorDefecto(),
                    'activo' => true,
                ],
            );
        }
    }
}
