<?php

namespace App\Facturacion\Repositories;

use App\Core\Base\BaseRepository;
use App\Facturacion\Models\AplicacionInteresFacturado;
use Illuminate\Database\Eloquent\Collection;

class AplicacionInteresFacturadoRepository extends BaseRepository
{
    public function __construct(AplicacionInteresFacturado $aplicacion)
    {
        parent::__construct($aplicacion);
    }

    /** Los pagos que cubrieron el interés de una línea de factura, para mostrar el enlace en el detalle. */
    public function porLinea(int $lineaDocumentoFacturacionId): Collection
    {
        return $this->consulta()
            ->with('pagoDetalle.pago')
            ->where('linea_documento_facturacion_id', $lineaDocumentoFacturacionId)
            ->get();
    }

    /** Si alguno de estos `pago_detalle` ya quedó enlazado a una factura de interés ya pagado. */
    public function existeParaPagoDetalleIds(array $pagoDetalleIds): bool
    {
        return $this->consulta()->whereIn('pago_detalle_id', $pagoDetalleIds)->exists();
    }
}
