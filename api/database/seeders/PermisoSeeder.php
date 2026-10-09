<?php

namespace Database\Seeders;

use App\Usuarios\Models\Permiso;
use Illuminate\Database\Seeder;

/**
 * Catálogo global de permisos (módulo/acción), réplica de permisos_base.sql
 * del legado. Es el único catálogo compartido entre empresas: la asignación
 * de estos permisos a roles concretos ocurre por empresa, al crearla (ver
 * EmpresaService y EmpresaConstants::PERMISOS_POR_ROL), no aquí.
 */
class PermisoSeeder extends Seeder
{
    public const CATALOGO = [
        'usuarios' => ['listar', 'crear', 'editar', 'eliminar'],
        'clientes' => ['listar', 'ver', 'crear', 'editar', 'exportar'],
        'creditos' => ['listar', 'crear', 'editar', 'aprobar', 'desembolsar', 'cancelar', 'eliminar', 'exportar', 'imprimir_estado_cuenta'],
        'pagos' => ['listar', 'crear', 'imprimir_recibo', 'exportar', 'reversar'],
        'recibos' => ['listar'],
        'cartera' => ['listar', 'exportar', 'imprimir_reporte_empleador', 'enviar_recordatorio'],
        'calendario' => ['listar'],
        'terceros' => ['listar', 'crear', 'editar'],
        'contabilidad' => ['listar', 'crear_manual', 'revisar', 'contabilizar', 'anular', 'imprimir_comprobante'],
        'configuracion_contable' => ['listar', 'crear', 'editar', 'configurar', 'cerrar_periodo', 'reabrir_periodo'],
        'informes_contables' => ['listar', 'exportar', 'imprimir'],
        'tributario' => ['listar', 'parametrizar'],
        'gastos' => ['listar', 'crear', 'anular', 'parametrizar', 'exportar'],
        'egresos' => ['listar', 'crear'],
        'auditoria' => ['listar', 'exportar'],
        'facturacion' => ['listar', 'crear', 'anular', 'imprimir', 'configurar'],
        'importaciones' => ['listar', 'ejecutar'],
        'metas' => ['listar', 'configurar', 'recalcular'],
    ];

    public function run(): void
    {
        foreach (self::CATALOGO as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                Permiso::query()->firstOrCreate(['modulo' => $modulo, 'accion' => $accion]);
            }
        }
    }
}
