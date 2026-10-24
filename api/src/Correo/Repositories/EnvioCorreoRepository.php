<?php

namespace App\Correo\Repositories;

use App\Core\Base\BaseRepository;
use App\Correo\Models\EnvioCorreo;
use Illuminate\Database\Eloquent\Collection;

class EnvioCorreoRepository extends BaseRepository
{
    public function __construct(EnvioCorreo $envio)
    {
        parent::__construct($envio);
    }

    /** Explícito por empresa: lo usa el job, que no tiene sesión. */
    public function deEmpresa(int $envioId, int $empresaId): ?EnvioCorreo
    {
        return $this->consulta()->where('empresa_id', $empresaId)->whereKey($envioId)->first();
    }

    /** `empresa_id` va explícito: un job que registra un envío no tiene sesión que lo rellene. */
    public function registrar(int $empresaId, array $datos): EnvioCorreo
    {
        $envio = new EnvioCorreo($datos);
        $envio->empresa_id = $empresaId;
        $envio->save();

        return $envio;
    }

    /** @return Collection<int, EnvioCorreo> */
    public function historialDeCliente(int $clienteId, int $limite = 20): Collection
    {
        return $this->consulta()
            ->where('cliente_id', $clienteId)
            ->orderByDesc('creado_en')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }
}
