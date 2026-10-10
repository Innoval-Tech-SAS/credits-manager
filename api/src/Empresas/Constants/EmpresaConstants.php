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
            'creditos.listar', 'creditos.imprimir_estado_cuenta',
            'pagos.listar', 'pagos.imprimir_recibo',
            'cartera.listar',
            'calendario.listar',
        ],
        'contable' => [
            'contabilidad.listar', 'contabilidad.crear_manual', 'contabilidad.revisar', 'contabilidad.contabilizar',
            'contabilidad.anular', 'contabilidad.imprimir_comprobante',
            'configuracion_contable.listar', 'configuracion_contable.crear', 'configuracion_contable.editar',
            'configuracion_contable.configurar', 'configuracion_contable.cerrar_periodo',
            'informes_contables.listar', 'informes_contables.exportar', 'informes_contables.imprimir',
            'terceros.listar', 'terceros.crear', 'terceros.editar',
            'tributario.listar', 'tributario.parametrizar',
            'facturacion.listar', 'facturacion.crear', 'facturacion.anular', 'facturacion.imprimir', 'facturacion.configurar',
            'gastos.listar', 'gastos.crear', 'gastos.anular', 'gastos.parametrizar', 'gastos.exportar',
            'egresos.listar', 'egresos.crear',
            'anticipos.listar', 'anticipos.crear',
            'cartera.listar', 'cartera.exportar', 'cartera.imprimir_reporte_empleador',
            'clientes.listar', 'clientes.ver', 'clientes.exportar',
            'pagos.exportar', 'pagos.reversar', 'recibos.listar',
            'conciliacion.listar', 'conciliacion.configurar', 'conciliacion.cargar_extracto',
            'conciliacion.conciliar', 'conciliacion.cerrar', 'conciliacion.exportar',
        ],
        'cartera' => [
            'clientes.listar', 'clientes.ver', 'clientes.editar', 'clientes.exportar',
            'creditos.listar', 'creditos.imprimir_estado_cuenta',
            'pagos.listar', 'pagos.crear', 'pagos.imprimir_recibo', 'pagos.exportar',
            'cartera.listar', 'cartera.enviar_recordatorio',
            'calendario.listar',
        ],
        'consulta' => [
            'usuarios.listar',
            'clientes.listar', 'clientes.ver',
            'creditos.listar', 'creditos.imprimir_estado_cuenta',
            'pagos.listar', 'pagos.imprimir_recibo',
            'cartera.listar',
            'calendario.listar',
            'contabilidad.listar', 'contabilidad.imprimir_comprobante',
            'configuracion_contable.listar', 'informes_contables.listar', 'terceros.listar',
            'conciliacion.listar',
        ],
        'auditoria' => [
            'contabilidad.listar', 'contabilidad.imprimir_comprobante',
            'configuracion_contable.listar', 'informes_contables.listar', 'informes_contables.exportar', 'informes_contables.imprimir', 'terceros.listar',
            'cartera.listar', 'cartera.exportar', 'cartera.imprimir_reporte_empleador',
            'auditoria.listar',
            'auditoria.exportar',
            'conciliacion.listar',
        ],
    ];
}
