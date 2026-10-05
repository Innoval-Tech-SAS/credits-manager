<?php

namespace App\Facturacion\Repositories;

use App\Core\Base\BaseRepository;
use App\Facturacion\Enums\MovimientoCobro;
use App\Facturacion\Models\CobroFacturacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CobroFacturacionRepository extends BaseRepository
{
    public function __construct(CobroFacturacion $cobro)
    {
        parent::__construct($cobro);
    }

    /** Centavos cobrados de una factura: cobros menos reversas. Usa el scope de empresa (la consulta es de una tabla). */
    public function cobradoNeto(int $documentoId): int
    {
        return (int) $this->consulta()
            ->where('documento_id', $documentoId)
            ->sum(DB::raw("CASE WHEN tipo = '".MovimientoCobro::Cobro->value."' THEN valor ELSE -valor END"));
    }

    /** @return Collection<int, CobroFacturacion> */
    public function listarDelDocumento(int $documentoId): Collection
    {
        return $this->consulta()
            ->with('registrador:id,nombre')
            ->where('documento_id', $documentoId)
            ->orderBy('id')
            ->get();
    }

    public function reversaDe(int $cobroId): ?CobroFacturacion
    {
        return $this->consulta()->where('cobro_origen_id', $cobroId)->first();
    }
}
