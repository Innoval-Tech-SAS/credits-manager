<?php

namespace App\Core\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Un asesor sin alcance global solo ve los clientes asignados a él y, por ellos, sus créditos y pagos.
 * Se suma a `EmpresaScope` (que separa empresas); el resto de usuarios y los contextos sin sesión
 * (artisan, colas, listener) no se filtran. `$columnaCliente` es la columna que apunta al cliente,
 * o `null` cuando el propio modelo es el cliente. Las cuotas, el calendario, la cartera y el dashboard
 * lo heredan al consultar por `whereHas('credito')`.
 */
class AlcanceAsesorScope implements Scope
{
    public function __construct(private readonly ?string $columnaCliente) {}

    public function apply(Builder $consulta, Model $modelo): void
    {
        $usuario = Auth::user();

        if (! $usuario?->restringidoAClientesPropios()) {
            return;
        }

        $tabla = $modelo->getTable();

        if ($this->columnaCliente === null) {
            $consulta->where("{$tabla}.asesor_id", $usuario->id);

            return;
        }

        $consulta->whereIn(
            "{$tabla}.{$this->columnaCliente}",
            DB::table('clientes')->select('id')->where('empresa_id', $usuario->empresa_id)->where('asesor_id', $usuario->id),
        );
    }
}
