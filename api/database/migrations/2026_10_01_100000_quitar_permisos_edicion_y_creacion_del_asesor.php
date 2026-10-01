<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El anexo técnico (sección 9) solo le da al asesor crear clientes, consultar lo asignado e imprimir
 * estados de cuenta; DDA confirmó que registrar pagos y crear créditos corresponde al rol `cartera` o
 * al admin, no al asesor. Revoca `clientes.editar`, `creditos.crear` y `pagos.crear` de los roles
 * `asesor` ya creados en empresas existentes. No toca `usuario_permiso`: las excepciones ya
 * personalizadas por usuario siguen vigentes tal cual.
 */
return new class extends Migration
{
    private const PERMISOS_A_REVOCAR = [
        ['modulo' => 'clientes', 'accion' => 'editar'],
        ['modulo' => 'creditos', 'accion' => 'crear'],
        ['modulo' => 'pagos', 'accion' => 'crear'],
    ];

    public function up(): void
    {
        $rolIds = DB::table('roles')->where('nombre', 'asesor')->pluck('id');

        DB::table('rol_permiso')
            ->whereIn('rol_id', $rolIds)
            ->whereIn('permiso_id', $this->idsDePermisos())
            ->delete();
    }

    public function down(): void
    {
        $permisoIds = $this->idsDePermisos();

        foreach (DB::table('roles')->where('nombre', 'asesor')->pluck('id') as $rolId) {
            foreach ($permisoIds as $permisoId) {
                DB::table('rol_permiso')->insertOrIgnore([
                    'rol_id' => $rolId,
                    'permiso_id' => $permisoId,
                    'creado_en' => now(),
                    'ultima_actualizacion' => now(),
                ]);
            }
        }
    }

    /** @return Collection<int, int> */
    private function idsDePermisos(): Collection
    {
        return collect(self::PERMISOS_A_REVOCAR)
            ->map(fn (array $clave) => DB::table('permisos')->where($clave)->value('id'))
            ->filter();
    }
};
