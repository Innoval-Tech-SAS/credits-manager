<?php

namespace App\Contabilidad\Constants;

class ContabilidadConstants
{
    public const COLUMNAS_ORDENABLES_CUENTAS = ['codigo', 'nombre'];

    public const COLUMNAS_ORDENABLES_COMPROBANTES = ['fecha', 'consecutivo', 'total_debito'];

    /** Caja y bancos son las cuentas de la clase 11 del PUC: la pantalla es el libro mayor limitado a ellas. */
    public const PREFIJO_CUENTAS_CAJA_BANCOS = '11';

    /**
     * Catálogos que otras operaciones consultan para elegir un valor (`algun_permiso`): basta con uno de estos
     * permisos. Administrarlos (crear, editar, eliminar) sigue exigiendo el permiso de su pantalla.
     */
    public const CONSULTA_PLAN_CUENTAS = [
        'configuracion_contable.listar_plan_cuentas', 'configuracion_contable.crear_plan_cuentas',
        'configuracion_contable.editar_plan_cuentas', 'configuracion_contable.configurar',
        'contabilidad.crear_manual', 'egresos.crear', 'anticipos.crear', 'gastos.crear_parametrizaciones',
        'informes_contables.listar_libro_mayor', 'informes_contables.listar_caja_bancos',
        'informes_contables.listar_auxiliares', 'facturacion.configurar', 'conciliacion.configurar',
    ];

    public const CONSULTA_TIPOS_COMPROBANTE = [
        'configuracion_contable.listar_tipos_comprobante', 'configuracion_contable.crear_tipos_comprobante',
        'configuracion_contable.editar_tipos_comprobante', 'configuracion_contable.configurar',
        'contabilidad.crear_manual', 'egresos.crear', 'anticipos.crear', 'gastos.crear_parametrizaciones',
        'conciliacion.conciliar',
    ];

    public const CONSULTA_CENTROS_COSTO = [
        'configuracion_contable.listar_centros_costo', 'configuracion_contable.crear_centros_costo',
        'configuracion_contable.editar_centros_costo', 'contabilidad.crear_manual', 'gastos.crear',
    ];

    /** Cuentas por pagar, por cobrar y anticipos comparten endpoint (`naturaleza`); `AccesoCarteraTerceros` decide por naturaleza. */
    public const CONSULTA_CARTERA_TERCEROS = [
        'contabilidad.listar_cuentas_por_pagar', 'contabilidad.listar_cuentas_por_cobrar', 'anticipos.listar', 'anticipos.crear',
    ];
}
