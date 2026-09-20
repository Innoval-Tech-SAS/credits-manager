<?php

namespace Database\Seeders;

use App\Usuarios\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Super-admin de la plataforma (gestiona el panel de empresas, sin
 * pertenecer a ninguna), a partir de las variables SEED_ADMIN_* del .env.
 * No tiene alta por API ni por consola: este seed es el único punto de
 * entrada inicial.
 */
class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
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
                'activo' => true,
                'es_superadmin' => true,
            ],
        );
    }
}
