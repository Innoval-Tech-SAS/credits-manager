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
            'clientes.listar', 'clientes.crear', 'clientes.editar',
            'creditos.listar', 'creditos.crear',
            'pagos.listar', 'pagos.crear',
            'cartera.listar',
            'calendario.listar',
        ],
        'contable' => [
            'contabilidad.listar', 'contabilidad.crear', 'contabilidad.editar',
            'contabilidad.configurar', 'contabilidad.crear_manual',
            'contabilidad.anular', 'contabilidad.cerrar_periodo', 'contabilidad.exportar',
            'gastos.listar', 'gastos.crear', 'gastos.anular', 'gastos.parametrizar',
            'cartera.listar', 'cartera.exportar',
            'clientes.listar',
        ],
        'cartera' => [
            'clientes.listar', 'clientes.editar',
            'creditos.listar',
            'pagos.listar', 'pagos.crear',
            'cartera.listar',
            'calendario.listar',
        ],
        'consulta' => [
            'usuarios.listar',
            'clientes.listar',
            'creditos.listar',
            'pagos.listar',
            'cartera.listar',
            'calendario.listar',
            'contabilidad.listar',
        ],
        'auditoria' => [
            'contabilidad.listar', 'contabilidad.exportar',
            'cartera.listar', 'cartera.exportar',
            'auditoria.listar',
            'auditoria.exportar',
        ],
    ];
}
