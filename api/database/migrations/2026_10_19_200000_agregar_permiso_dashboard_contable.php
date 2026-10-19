<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de datos para empresas ya existentes: `PermisoSeeder`/`EmpresaConstants::PERMISOS_POR_ROL` solo asignan
 * permisos nuevos a roles creados a partir de ahora (`EmpresaService::crearRolesBase`), así que el permiso nuevo del
 * dashboard contable (`informes_contables.ver_dashboard_contable`) necesita esta migración para llegar a los roles
 * `admin`, `contable`, `consulta` y `auditoria` de las empresas que ya existían. Idempotente: se puede correr sobre
 * una base que ya la tenga aplicada sin duplicar filas.
 */
return new class extends Migration
{
    private const MODULO = 'informes_contables';

    private const ACCION = 'ver_dashboard_contable';

    private const ROLES = ['admin', 'contable', 'consulta', 'auditoria'];

    public function up(): void
    {
        $permiso = DB::table('permisos')->where('modulo', self::MODULO)->where('accion', self::ACCION)->first();

        if ($permiso === null) {
            $permisoId = DB::table('permisos')->insertGetId([
                'modulo' => self::MODULO,
                'accion' => self::ACCION,
                'creado_en' => now(),
                'ultima_actualizacion' => now(),
            ]);
        } else {
            $permisoId = $permiso->id;

            if ($permiso->borrado_en !== null) {
                DB::table('permisos')->where('id', $permisoId)->update(['borrado_en' => null, 'ultima_actualizacion' => now()]);
            }
        }

        $rolesDeEmpresa = DB::table('roles')->whereNotNull('empresa_id')->whereIn('nombre', self::ROLES)->pluck('id');

        $yaAsignados = DB::table('rol_permiso')->where('permiso_id', $permisoId)->whereIn('rol_id', $rolesDeEmpresa)->pluck('rol_id');

        $filas = $rolesDeEmpresa->diff($yaAsignados)->map(fn (int $rolId) => [
            'rol_id' => $rolId,
            'permiso_id' => $permisoId,
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);

        if ($filas->isNotEmpty()) {
            DB::table('rol_permiso')->insert($filas->all());
        }
    }

    public function down(): void
    {
        $permiso = DB::table('permisos')->where('modulo', self::MODULO)->where('accion', self::ACCION)->first();

        if ($permiso === null) {
            return;
        }

        DB::table('rol_permiso')->where('permiso_id', $permiso->id)->delete();
        DB::table('permisos')->where('id', $permiso->id)->update(['borrado_en' => now()]);
    }
};
