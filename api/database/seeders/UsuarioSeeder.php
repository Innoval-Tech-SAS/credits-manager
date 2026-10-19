<?php

namespace Database\Seeders;

use App\Usuarios\Constants\UsuarioConstants;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use App\Usuarios\Repositories\RolRepository;
use Illuminate\Database\Seeder;

/**
 * Super Admin de la plataforma (gestiona el panel de empresas, sin
 * pertenecer a ninguna), a partir de las variables SEED_ADMIN_* del .env.
 * No tiene alta por API ni por consola: este seed es el único punto de
 * entrada inicial. La base admite un solo Super Admin vivo.
 */
class UsuarioSeeder extends Seeder
{
    public function run(RolRepository $roles): void
    {
        Rol::withoutGlobalScopes()->firstOrCreate(
            ['empresa_id' => null, 'nombre' => UsuarioConstants::ROL_SUPERADMIN],
            ['descripcion' => 'Administra la plataforma y el panel de empresas'],
        );

        $usuario = strtolower((string) env('SEED_ADMIN_USUARIO'));
        $password = env('SEED_ADMIN_PASSWORD');

        if ($usuario === '' || ! $password) {
            return;
        }

        Usuario::query()->firstOrCreate(
            ['usuario' => $usuario],
            [
                'nombre' => env('SEED_ADMIN_NOMBRE', 'Administrador'),
                'password_hash' => $password,
                'rol_id' => $roles->rolSuperAdmin()->id,
                'activo' => true,
            ],
        );
    }
}
