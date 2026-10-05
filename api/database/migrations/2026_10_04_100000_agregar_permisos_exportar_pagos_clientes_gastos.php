<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos de exportación de pagos, clientes y gastos. El catálogo y PERMISOS_POR_ROL solo aplican al crear una
 * empresa, así que cada permiso nuevo se concede a los roles base que deben tenerlo en las empresas que ya existen.
 * No se tocan las excepciones por usuario.
 */
return new class extends Migration
{
    /** 'modulo.accion' nuevo => roles base que lo reciben. El rol admin recibe todo el catálogo. */
    private const ROLES = [
        'pagos.exportar' => ['admin', 'contable', 'cartera'],
        'clientes.exportar' => ['admin', 'contable', 'cartera'],
        'gastos.exportar' => ['admin', 'contable'],
    ];

    public function up(): void
    {
        foreach (self::ROLES as $clave => $roles) {
            $permisoId = $this->asegurarPermiso($clave);

            foreach ($roles as $nombreRol) {
                foreach (DB::table('roles')->where('nombre', $nombreRol)->pluck('id') as $rolId) {
                    DB::table('rol_permiso')->insertOrIgnore([
                        'rol_id' => $rolId,
                        'permiso_id' => $permisoId,
                        'creado_en' => now(),
                        'ultima_actualizacion' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $permisoIds = collect(array_keys(self::ROLES))->map(fn (string $clave) => $this->idDe($clave))->filter();

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }

    private function asegurarPermiso(string $clave): int
    {
        [$modulo, $accion] = explode('.', $clave);

        DB::table('permisos')->updateOrInsert(
            ['modulo' => $modulo, 'accion' => $accion],
            ['creado_en' => now(), 'ultima_actualizacion' => now()],
        );

        return $this->idDe($clave);
    }

    private function idDe(string $clave): ?int
    {
        [$modulo, $accion] = explode('.', $clave);

        return DB::table('permisos')->where(['modulo' => $modulo, 'accion' => $accion])->value('id');
    }
};
