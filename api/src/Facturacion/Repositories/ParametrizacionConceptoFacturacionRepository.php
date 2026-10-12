<?php

namespace App\Facturacion\Repositories;

use App\Core\Base\BaseRepository;
use App\Facturacion\Enums\TipoConceptoFacturacion;
use App\Facturacion\Models\ParametrizacionConceptoFacturacion;
use Illuminate\Support\Collection;

class ParametrizacionConceptoFacturacionRepository extends BaseRepository
{
    public function __construct(ParametrizacionConceptoFacturacion $parametrizacion)
    {
        parent::__construct($parametrizacion);
    }

    /** @return Collection<string, ParametrizacionConceptoFacturacion> las 4 filas de la empresa, indexadas por tipo_concepto */
    public function paraEmpresa(int $empresaId): Collection
    {
        return $this->consulta()
            ->where('empresa_id', $empresaId)
            ->get()
            ->keyBy(fn (ParametrizacionConceptoFacturacion $fila) => $fila->tipo_concepto->value);
    }

    public function deConcepto(int $empresaId, TipoConceptoFacturacion $tipoConcepto): ?ParametrizacionConceptoFacturacion
    {
        return $this->consulta()
            ->where('empresa_id', $empresaId)
            ->where('tipo_concepto', $tipoConcepto)
            ->first();
    }

    /** Fila por defecto (todo en cero/false) para cada uno de los 4 conceptos: la usa el alta de empresa. */
    public function crearPorDefectoParaEmpresa(int $empresaId): void
    {
        foreach (TipoConceptoFacturacion::cases() as $tipoConcepto) {
            $this->consulta()->firstOrCreate(['empresa_id' => $empresaId, 'tipo_concepto' => $tipoConcepto]);
        }
    }
}
