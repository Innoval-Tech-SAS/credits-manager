<?php

namespace App\Gastos\Repositories;

use App\Core\Base\BaseRepository;
use App\Core\Scopes\EmpresaScope;
use App\Gastos\Models\Gasto;
use App\Gastos\Models\ValorUvt;
use Illuminate\Database\Eloquent\Collection;

class ValorUvtRepository extends BaseRepository
{
    public function __construct(ValorUvt $valorUvt)
    {
        parent::__construct($valorUvt);
    }

    public function todos(): Collection
    {
        return $this->consulta()->orderByDesc('anio')->get();
    }

    public function paraAnio(int $anio): ?ValorUvt
    {
        return $this->consulta()->where('anio', $anio)->first();
    }

    public function guardar(int $anio, float $valor): ValorUvt
    {
        return $this->consulta()->updateOrCreate(['anio' => $anio], ['valor' => $valor]);
    }

    /** La UVT es global (sin `empresa_id`): protege el valor si algún gasto de CUALQUIER empresa ya se causó con ese año. */
    public function tieneGastosEnAnio(int $anio): bool
    {
        return Gasto::withoutGlobalScope(EmpresaScope::class)->whereYear('fecha', $anio)->exists();
    }
}
