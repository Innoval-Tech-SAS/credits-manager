<?php

namespace App\Empresas\Constants;

class EmpresaConstants
{
    /** Roles que se crean automáticamente para cada empresa nueva: nombre => descripción. */
    public const ROLES_BASE = [
        'admin' => 'Acceso total al sistema',
        'asesor' => 'Gestión de clientes y créditos asignados',
        'contable' => 'Gestión del módulo contable y financiero',
        'cartera' => 'Gestión de cobranza y cartera',
        'consulta' => 'Solo lectura de reportes autorizados',
        'auditoria' => 'Consulta de bitácora y reportes de control',
    ];

    /**
     * Permisos ("modulo.accion") que recibe cada rol base al crear la empresa.
     * El rol 'admin' no aparece aquí: recibe automáticamente todo el catálogo.
     */
    public const PERMISOS_POR_ROL = [
        'asesor' => [
            'clientes.listar', 'clientes.ver', 'clientes.crear',
            'empleadores.listar', 'empleadores.ver', 'empleadores.crear',
            'creditos.listar', 'creditos.imprimir_estado_cuenta',
            'pagos.listar', 'pagos.imprimir_recibo',
            'cartera.listar',
            'calendario.listar',
        ],
        'contable' => [
            'contabilidad.listar_comprobantes', 'contabilidad.listar_cuentas_por_pagar',
            'contabilidad.listar_cuentas_por_cobrar', 'contabilidad.crear_manual', 'contabilidad.revisar',
            'contabilidad.contabilizar', 'contabilidad.anular', 'contabilidad.imprimir_comprobante',
            'configuracion_contable.listar_plan_cuentas', 'configuracion_contable.listar_tipos_comprobante',
            'configuracion_contable.listar_centros_costo', 'configuracion_contable.listar_periodos',
            'configuracion_contable.crear_plan_cuentas', 'configuracion_contable.crear_tipos_comprobante',
            'configuracion_contable.crear_centros_costo', 'configuracion_contable.editar_plan_cuentas',
            'configuracion_contable.editar_tipos_comprobante', 'configuracion_contable.eliminar_tipos_comprobante',
            'configuracion_contable.editar_centros_costo', 'configuracion_contable.eliminar_centros_costo',
            'configuracion_contable.configurar', 'configuracion_contable.cerrar_periodo',
            'informes_contables.listar_libro_diario', 'informes_contables.listar_libro_mayor',
            'informes_contables.listar_caja_bancos', 'informes_contables.listar_balance_prueba',
            'informes_contables.listar_estados_financieros', 'informes_contables.listar_auxiliares',
            'informes_contables.listar_conciliacion_apertura', 'informes_contables.exportar',
            'informes_contables.imprimir',
            'terceros.listar', 'terceros.crear', 'terceros.editar',
            'tributario.listar_tarifas_reteica', 'tributario.listar_actividades_economicas',
            'tributario.listar_valores_uvt', 'tributario.crear_tarifas_reteica',
            'tributario.crear_actividades_economicas', 'tributario.editar_actividades_economicas',
            'tributario.crear_valores_uvt', 'tributario.editar_valores_uvt', 'tributario.eliminar_valores_uvt',
            'facturacion.listar_facturas', 'facturacion.listar_notas_credito', 'facturacion.listar_notas_debito',
            'facturacion.crear', 'facturacion.anular', 'facturacion.anular_nota_credito',
            'facturacion.anular_nota_debito', 'facturacion.imprimir', 'facturacion.configurar',
            'facturacion.modificar_valores_sugeridos', 'facturacion.registrar_cobro', 'facturacion.reversar_cobro',
            'gastos.listar_gastos', 'gastos.listar_gastos_centro_costo', 'gastos.listar_tipos_gasto',
            'gastos.listar_parametrizaciones', 'gastos.crear', 'gastos.anular', 'gastos.crear_tipos_gasto',
            'gastos.editar_tipos_gasto', 'gastos.eliminar_tipos_gasto', 'gastos.crear_parametrizaciones',
            'gastos.exportar',
            'egresos.listar', 'egresos.crear', 'egresos.reversar',
            'anticipos.listar', 'anticipos.crear',
            'cartera.listar', 'cartera.exportar', 'cartera.imprimir_reporte_empleador',
            'clientes.listar', 'clientes.ver', 'clientes.exportar',
            'empleadores.listar', 'empleadores.ver',
            'pagos.exportar', 'pagos.reversar',
            'recibos.listar',
            'conciliacion.listar', 'conciliacion.configurar', 'conciliacion.cargar_extracto', 'conciliacion.conciliar',
            'conciliacion.cerrar', 'conciliacion.exportar',
        ],
        'cartera' => [
            'clientes.listar', 'clientes.ver', 'clientes.editar', 'clientes.exportar',
            'empleadores.listar', 'empleadores.ver', 'empleadores.editar',
            'creditos.listar', 'creditos.imprimir_estado_cuenta',
            'pagos.listar', 'pagos.crear', 'pagos.imprimir_recibo', 'pagos.exportar',
            'cartera.listar', 'cartera.enviar_recordatorio',
            'calendario.listar',
        ],
        'consulta' => [
            'usuarios.listar',
            'clientes.listar', 'clientes.ver',
            'empleadores.listar', 'empleadores.ver',
            'creditos.listar', 'creditos.imprimir_estado_cuenta',
            'pagos.listar', 'pagos.imprimir_recibo',
            'cartera.listar',
            'calendario.listar',
            'contabilidad.listar_comprobantes', 'contabilidad.listar_cuentas_por_pagar',
            'contabilidad.listar_cuentas_por_cobrar', 'contabilidad.imprimir_comprobante',
            'configuracion_contable.listar_plan_cuentas', 'configuracion_contable.listar_tipos_comprobante',
            'configuracion_contable.listar_centros_costo', 'configuracion_contable.listar_periodos',
            'informes_contables.listar_libro_diario', 'informes_contables.listar_libro_mayor',
            'informes_contables.listar_caja_bancos', 'informes_contables.listar_balance_prueba',
            'informes_contables.listar_estados_financieros', 'informes_contables.listar_auxiliares',
            'informes_contables.listar_conciliacion_apertura',
            'terceros.listar',
            'conciliacion.listar',
        ],
        'auditoria' => [
            'contabilidad.listar_comprobantes', 'contabilidad.listar_cuentas_por_pagar',
            'contabilidad.listar_cuentas_por_cobrar', 'contabilidad.imprimir_comprobante',
            'configuracion_contable.listar_plan_cuentas', 'configuracion_contable.listar_tipos_comprobante',
            'configuracion_contable.listar_centros_costo', 'configuracion_contable.listar_periodos',
            'informes_contables.listar_libro_diario', 'informes_contables.listar_libro_mayor',
            'informes_contables.listar_caja_bancos', 'informes_contables.listar_balance_prueba',
            'informes_contables.listar_estados_financieros', 'informes_contables.listar_auxiliares',
            'informes_contables.listar_conciliacion_apertura', 'informes_contables.exportar',
            'informes_contables.imprimir',
            'terceros.listar',
            'cartera.listar', 'cartera.exportar', 'cartera.imprimir_reporte_empleador',
            'auditoria.listar', 'auditoria.exportar',
            'conciliacion.listar',
        ],
    ];
}
