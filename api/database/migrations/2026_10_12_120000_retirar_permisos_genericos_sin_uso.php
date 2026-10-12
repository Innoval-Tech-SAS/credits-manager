<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Retira los permisos genéricos que ya no controlan nada: las separaciones de módulos (`separar_permisos_contabilidad_
 * en_modulos`) y por pantalla (`separar_permisos_por_pantalla`) los tradujeron a permisos propios y el código dejó de
 * consultarlos, pero seguían en el catálogo y en el formulario de roles. Se retiran con borrado lógico (`borrado_en`):
 * el modelo los excluye del catálogo, de los roles, de las excepciones por usuario y de `tienePermiso`, y las filas de
 * `rol_permiso`/`usuario_permiso` se conservan, así que `down()` los devuelve exactamente como estaban.
 */
return new class extends Migration
{
    private const RETIRADOS = [
        'contabilidad.listar', 'contabilidad.crear', 'contabilidad.editar', 'contabilidad.configurar',
        'contabilidad.cerrar_periodo', 'contabilidad.reabrir_periodo', 'contabilidad.exportar',
        'configuracion_contable.listar', 'configuracion_contable.crear', 'configuracion_contable.editar',
        'informes_contables.listar',
        'tributario.listar', 'tributario.parametrizar',
        'gastos.listar', 'gastos.parametrizar',
        'facturacion.listar',
    ];

    public function up(): void
    {
        $this->retirados()->whereNull('borrado_en')->update(['borrado_en' => now(), 'ultima_actualizacion' => now()]);
    }

    public function down(): void
    {
        $this->retirados()->whereNotNull('borrado_en')->update(['borrado_en' => null, 'ultima_actualizacion' => now()]);
    }

    private function retirados(): Builder
    {
        return DB::table('permisos')->where(function (Builder $consulta) {
            foreach (self::RETIRADOS as $clave) {
                [$modulo, $accion] = explode('.', $clave);
                $consulta->orWhere(fn (Builder $condicion) => $condicion->where('modulo', $modulo)->where('accion', $accion));
            }
        });
    }
};
