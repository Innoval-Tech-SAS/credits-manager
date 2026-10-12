<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Un permiso por pantalla del menú: el módulo es el grupo del menú y la acción, la operación sobre el sub-item
 * (`facturacion.listar_notas_credito`, `configuracion_contable.crear_centros_costo`). Mismo patrón que
 * `separar_permisos_contabilidad_en_modulos`: cada permiso genérico se traduce a los nuevos que dan la misma acción,
 * en roles y en excepciones por usuario (mismo `concedido`), así que nadie gana ni pierde acceso al desplegar. Los
 * genéricos se conservan (la migración solo agrega) aunque el código ya no los consulta; `down()` quita lo agregado.
 */
return new class extends Migration
{
    /** 'modulo.accion' genérico => 'modulo.accion' por pantalla que dan exactamente lo mismo. */
    private const TRADUCCIONES = [
        'contabilidad.listar' => [
            'contabilidad.listar_comprobantes', 'contabilidad.listar_cuentas_por_pagar', 'contabilidad.listar_cuentas_por_cobrar',
        ],
        'configuracion_contable.listar' => [
            'configuracion_contable.listar_plan_cuentas', 'configuracion_contable.listar_tipos_comprobante',
            'configuracion_contable.listar_centros_costo', 'configuracion_contable.listar_periodos',
        ],
        'configuracion_contable.crear' => [
            'configuracion_contable.crear_plan_cuentas', 'configuracion_contable.crear_tipos_comprobante',
            'configuracion_contable.crear_centros_costo',
        ],
        'configuracion_contable.editar' => [
            'configuracion_contable.editar_plan_cuentas', 'configuracion_contable.editar_tipos_comprobante',
            'configuracion_contable.eliminar_tipos_comprobante', 'configuracion_contable.editar_centros_costo',
            'configuracion_contable.eliminar_centros_costo',
        ],
        'informes_contables.listar' => [
            'informes_contables.listar_libro_diario', 'informes_contables.listar_libro_mayor', 'informes_contables.listar_caja_bancos',
            'informes_contables.listar_balance_prueba', 'informes_contables.listar_estados_financieros',
            'informes_contables.listar_auxiliares', 'informes_contables.listar_conciliacion_apertura',
        ],
        'tributario.listar' => [
            'tributario.listar_tarifas_reteica', 'tributario.listar_actividades_economicas', 'tributario.listar_valores_uvt',
        ],
        'tributario.parametrizar' => [
            'tributario.crear_tarifas_reteica', 'tributario.crear_actividades_economicas', 'tributario.editar_actividades_economicas',
            'tributario.crear_valores_uvt', 'tributario.editar_valores_uvt', 'tributario.eliminar_valores_uvt',
        ],
        'gastos.listar' => [
            'gastos.listar_gastos', 'gastos.listar_gastos_centro_costo', 'gastos.listar_tipos_gasto', 'gastos.listar_parametrizaciones',
        ],
        'gastos.parametrizar' => [
            'gastos.crear_tipos_gasto', 'gastos.editar_tipos_gasto', 'gastos.eliminar_tipos_gasto', 'gastos.crear_parametrizaciones',
        ],
        'facturacion.listar' => [
            'facturacion.listar_facturas', 'facturacion.listar_notas_credito', 'facturacion.listar_notas_debito',
        ],
    ];

    public function up(): void
    {
        $nuevos = collect(self::TRADUCCIONES)->flatten()->unique()->values();

        foreach ($nuevos as $clave) {
            [$modulo, $accion] = explode('.', $clave);

            DB::table('permisos')->insertOrIgnore([
                'modulo' => $modulo,
                'accion' => $accion,
                'creado_en' => now(),
                'ultima_actualizacion' => now(),
            ]);
        }

        foreach (self::TRADUCCIONES as $origen => $destinos) {
            $origenId = $this->idDe($origen);

            if ($origenId === null) {
                continue;
            }

            foreach (DB::table('rol_permiso')->where('permiso_id', $origenId)->pluck('rol_id') as $rolId) {
                foreach ($destinos as $destino) {
                    $this->asignarARol((int) $rolId, $this->idDe($destino));
                }
            }

            foreach (DB::table('usuario_permiso')->where('permiso_id', $origenId)->get(['usuario_id', 'concedido']) as $excepcion) {
                foreach ($destinos as $destino) {
                    $this->asignarAUsuario((int) $excepcion->usuario_id, $this->idDe($destino), (bool) $excepcion->concedido);
                }
            }
        }

        foreach (DB::table('roles')->where('nombre', 'admin')->pluck('id') as $rolId) {
            foreach ($nuevos as $clave) {
                $this->asignarARol((int) $rolId, $this->idDe($clave));
            }
        }
    }

    public function down(): void
    {
        $permisoIds = collect(self::TRADUCCIONES)->flatten()->unique()
            ->map(fn (string $clave) => $this->idDe($clave))
            ->filter()
            ->values();

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }

    private function asignarARol(int $rolId, ?int $permisoId): void
    {
        if ($permisoId === null) {
            return;
        }

        DB::table('rol_permiso')->insertOrIgnore([
            'rol_id' => $rolId,
            'permiso_id' => $permisoId,
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);
    }

    private function asignarAUsuario(int $usuarioId, ?int $permisoId, bool $concedido): void
    {
        if ($permisoId === null) {
            return;
        }

        DB::table('usuario_permiso')->insertOrIgnore([
            'usuario_id' => $usuarioId,
            'permiso_id' => $permisoId,
            'concedido' => $concedido,
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);
    }

    private function idDe(string $clave): ?int
    {
        [$modulo, $accion] = explode('.', $clave);
        $id = DB::table('permisos')->where(['modulo' => $modulo, 'accion' => $accion])->value('id');

        return $id === null ? null : (int) $id;
    }
};
