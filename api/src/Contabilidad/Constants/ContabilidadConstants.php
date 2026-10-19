<?php

namespace App\Contabilidad\Constants;

class ContabilidadConstants
{
    public const COLUMNAS_ORDENABLES_CUENTAS = ['codigo', 'nombre'];

    public const COLUMNAS_ORDENABLES_COMPROBANTES = ['fecha', 'consecutivo', 'total_debito'];

    /** Caja y bancos son las cuentas de la clase 11 del PUC: la pantalla es el libro mayor limitado a ellas. */
    public const PREFIJO_CUENTAS_CAJA_BANCOS = '11';

    /** Prefijos PUC que usan los controles de cierre mensual (6.6.3): CxC (deudoras) y CxP (acreedoras) por edades. */
    public const PREFIJOS_CUENTAS_POR_COBRAR = ['1305'];

    public const PREFIJOS_CUENTAS_POR_PAGAR = ['2205', '2335'];

    /** Deterioro (provisión) de cartera: cuenta correctora del activo, naturaleza crédito dentro de 1305. */
    public const PREFIJO_CUENTA_DETERIORO_CARTERA = '1399';

    /** Clave de la tarea programada que el dashboard contable consulta para "devengo de intereses este mes". */
    public const TAREA_DEVENGAR_INTERESES = 'contabilidad:devengar-intereses';

    public const LIMITE_DOCUMENTOS_CRUCE = 100;

    /**
     * Los reportes leen código, naturaleza y tipo de `plan_cuentas` al consultarse (los asientos solo guardan `cuenta_id`):
     * cambiarlos en una cuenta con movimientos reescribiría el histórico, incluidos los períodos cerrados.
     */
    public const CAMPOS_ESTRUCTURALES_CUENTA = ['codigo', 'naturaleza', 'tipo'];

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

    public const CONSULTA_TIPOS_DOCUMENTO_NEGOCIO = [
        'configuracion_contable.listar_tipos_documento_negocio', 'configuracion_contable.editar_tipos_documento_negocio',
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
