<?php

namespace App\Gastos\Services;

use App\Gastos\Models\ValorUvt;
use App\Gastos\Repositories\ValorUvtRepository;
use Illuminate\Database\Eloquent\Collection;

class ValorUvtService
{
    public function __construct(protected ValorUvtRepository $valoresUvt) {}

    public function listar(): Collection
    {
        return $this->valoresUvt->todos();
    }

    public function guardar(int $anio, float $valor): ValorUvt
    {
        return $this->valoresUvt->guardar($anio, $valor);
    }
}
