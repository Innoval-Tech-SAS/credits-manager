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
            'clientes.listar', 'clientes.ver', 'clientes.crear', 'clientes.editar',
            'creditos.listar', 'creditos.crear', 'creditos.imprimir_estado_cuenta',
            'pagos.listar', 'pagos.crear', 'pagos.imprimir_recibo',
            'cartera.listar',
            'calendario.listar',
        ],
        'contable' => [
            'contabilidad.listar', 'contabilidad.crear', 'contabilidad.editar',
            'contabilidad.configurar', 'contabilidad.crear_manual',
            'contabilidad.anular', 'contabilidad.cerrar_periodo', 'contabilidad.exportar', 'contabilidad.imprimir_comprobante',
            'gastos.listar', 'gastos.crear', 'gastos.anular', 'gastos.parametrizar',
            'cartera.listar', 'cartera.exportar', 'cartera.imprimir_reporte_empleador',
            'clientes.listar', 'clientes.ver',
        ],
        'cartera' => [
            'clientes.listar', 'clientes.ver', 'clientes.editar',
            'creditos.listar', 'creditos.imprimir_estado_cuenta',
            'pagos.listar', 'pagos.crear', 'pagos.imprimir_recibo',
            'cartera.listar',
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
        ],
        'auditoria' => [
            'contabilidad.listar', 'contabilidad.exportar', 'contabilidad.imprimir_comprobante',
            'cartera.listar', 'cartera.exportar', 'cartera.imprimir_reporte_empleador',
            'auditoria.listar',
            'auditoria.exportar',
        ],
    ];
}
