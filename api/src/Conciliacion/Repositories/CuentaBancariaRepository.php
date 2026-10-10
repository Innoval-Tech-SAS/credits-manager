<?php

namespace App\Conciliacion\Repositories;

use App\Conciliacion\Models\CuentaBancaria;
use App\Core\Base\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class CuentaBancariaRepository extends BaseRepository
{
    public function __construct(CuentaBancaria $cuentaBancaria)
    {
        parent::__construct($cuentaBancaria);
    }

    public function todas(): Collection
    {
        return $this->consulta()->with(['planCuenta:id,codigo,nombre', 'planCuentaPuente:id,codigo,nombre'])->orderBy('alias')->orderBy('numero_cuenta')->get();
    }
}
