<?php

namespace App\Exportacion\Constants;

class ExportacionConstants
{
    /** Límite de Excel: 1 048 576 filas por hoja, una de ellas es el encabezado. */
    public const FILAS_MAXIMAS = 1048575;

    public const DISCO = 'exportaciones';

    /** Conexión de cola explícita: no depende de QUEUE_CONNECTION. */
    public const CONEXION_COLA = 'redis';

    public const COLA = 'exportaciones';

    public const TIMEOUT_JOB_SEGUNDOS = 3600;

    /**
     * Única fuente de verdad del permiso de cada exportación: clave del reporte (`FuenteExportable::reporte()`) => permisos
     * "modulo.accion" que la autorizan (basta uno). La leen la ruta (middleware `exportacion`), la fuente al solicitar y
     * la descarga. Un reporte que no esté aquí no se autoriza a nadie.
     */
    public const PERMISOS_POR_REPORTE = [
        'clientes.listado' => ['clientes.exportar_listado'],
        'creditos.listado' => ['creditos.exportar_listado'],
        'creditos.estado_cuenta' => ['creditos.exportar_estado_cuenta'],
        'cartera.listado' => ['cartera.exportar_listado'],
        'cartera.reporte_empleador' => ['cartera.exportar_reporte_empleador'],
        'pagos.listado' => ['pagos.exportar_listado'],
        'gastos.listado' => ['gastos.exportar_gastos'],
        'gastos.centro_costo' => ['gastos.exportar_gastos_centro_costo'],
        'contabilidad.libro_diario' => ['informes_contables.exportar_libro_diario'],
        'contabilidad.libro_mayor' => ['informes_contables.exportar_libro_mayor'],
        // Caja y bancos exporta el libro mayor de una cuenta de la clase 11: lo autoriza también quien exporta el mayor.
        'contabilidad.caja_bancos' => ['informes_contables.exportar_caja_bancos', 'informes_contables.exportar_libro_mayor'],
        'contabilidad.balance_prueba' => ['informes_contables.exportar_balance_prueba'],
        'contabilidad.estados_financieros' => ['informes_contables.exportar_estados_financieros'],
        'contabilidad.auxiliar' => ['informes_contables.exportar_auxiliares'],
        'auditoria.bitacora' => ['auditoria.exportar_bitacora'],
    ];

    /** Reportes de plataforma que el superadmin (sin rol ni permisos) exporta igual que en `AccesoAuditoria`. */
    public const REPORTES_DE_PLATAFORMA = ['auditoria.bitacora'];

    public const HOJA_DATOS = 'Datos';

    public const HOJA_PARAMETROS = 'Parámetros';

    public const FORMATO_MONETARIO = '#,##0.00';

    public const FORMATO_DECIMAL = '#,##0.00##';

    public const FORMATO_PORCENTAJE = '0.00%';

    public const FORMATO_FECHA = 'yyyy-mm-dd';

    public const FORMATO_FECHA_HORA = 'yyyy-mm-dd hh:mm:ss';
}
