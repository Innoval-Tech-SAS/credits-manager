<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Retira `conciliacion.exportar`: la conciliación bancaria no tiene exportación y el permiso no controla nada. Como en
 * `retirar_permisos_genericos_sin_uso`, se retira con borrado lógico (`borrado_en`): sale del catálogo, de los roles y de
 * `tienePermiso`, y las filas de `rol_permiso`/`usuario_permiso` se conservan, así que `down()` lo devuelve tal cual.
 * Si algún día se construye esa exportación, su permiso nace con su reporte en `ExportacionConstants`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->retirado()->whereNull('borrado_en')->update(['borrado_en' => now(), 'ultima_actualizacion' => now()]);
    }

    public function down(): void
    {
        $this->retirado()->whereNotNull('borrado_en')->update(['borrado_en' => null, 'ultima_actualizacion' => now()]);
    }

    private function retirado(): Builder
    {
        return DB::table('permisos')->where(['modulo' => 'conciliacion', 'accion' => 'exportar']);
    }
};
