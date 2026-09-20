<?php

namespace App\Usuarios\Repositories;

use App\Core\Base\BaseRepository;
use App\Usuarios\Constants\UsuarioConstants;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class UsuarioRepository extends BaseRepository
{
    public function __construct(Usuario $usuario)
    {
        parent::__construct($usuario);
    }

    public function buscarActivoPorUsuario(string $usuario): ?Usuario
    {
        return $this->consulta()
            ->where('usuario', $usuario)
            ->where('activo', true)
            ->first();
    }

    public function paginar(?string $busqueda, ?string $columna, string $direccion, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta()->with('rol');

        if ($busqueda) {
            $consulta->where(function (Builder $subconsulta) use ($busqueda) {
                $subconsulta->where('nombre', 'like', "%{$busqueda}%")
                    ->orWhere('usuario', 'like', "%{$busqueda}%");
            });
        }

        if ($columna) {
            $consulta->orderBy($columna, $direccion);
        }

        return $consulta->paginate(perPage: $porPagina, page: $pagina);
    }

    public function sincronizarPermisosExtra(Usuario $usuario, array $mapa): void
    {
        $usuario->permisosExtra()->sync($mapa);
    }

    public function contarAdminsActivos(): int
    {
        return $this->consulta()
            ->whereHas('rol', fn (Builder $consulta) => $consulta->where('nombre', UsuarioConstants::ROL_ADMIN))
            ->where('activo', true)
            ->count();
    }
}
