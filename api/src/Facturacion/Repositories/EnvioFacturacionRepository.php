<?php

namespace App\Facturacion\Repositories;

use App\Core\Base\BaseRepository;
use App\Facturacion\Models\EnvioFacturacion;
use Illuminate\Database\Eloquent\Collection;

class EnvioFacturacionRepository extends BaseRepository
{
    public function __construct(EnvioFacturacion $envio)
    {
        parent::__construct($envio);
    }

    /** Explícito por empresa: lo usa el job, que no tiene sesión. */
    public function deEmpresa(int $envioId, int $empresaId): ?EnvioFacturacion
    {
        return $this->consulta()->where('empresa_id', $empresaId)->whereKey($envioId)->first();
    }

    /** @return Collection<int, EnvioFacturacion> */
    public function deDocumento(int $documentoId): Collection
    {
        return $this->consulta()
            ->where('documento_facturacion_id', $documentoId)
            ->orderByDesc('creado_en')
            ->orderByDesc('id')
            ->get();
    }

    /** `empresa_id` va explícito: lo reutilizará el job, que no tiene sesión que lo rellene. */
    public function registrar(int $empresaId, array $datos): EnvioFacturacion
    {
        $envio = new EnvioFacturacion($datos);
        $envio->empresa_id = $empresaId;
        $envio->save();

        return $envio;
    }
}
