<?php

use App\Auditoria\Controllers\BitacoraAuditoriaController;
use App\Auditoria\Controllers\RecuperacionAuditoriaController;
use App\Auth\Controllers\AuthController;
use App\Calendario\Controllers\CalendarioController;
use App\Cartera\Controllers\CarteraController;
use App\Clientes\Controllers\ClienteController;
use App\Contabilidad\Controllers\AuxiliarContableController;
use App\Contabilidad\Controllers\CentroCostoController;
use App\Contabilidad\Controllers\ComprobanteController;
use App\Contabilidad\Controllers\DocumentoTerceroController;
use App\Contabilidad\Controllers\PeriodoContableController;
use App\Contabilidad\Controllers\PlanCuentaController;
use App\Contabilidad\Controllers\ReglaContabilizacionController;
use App\Contabilidad\Controllers\ReporteContableController;
use App\Contabilidad\Controllers\SolicitudReaperturaPeriodoController;
use App\Contabilidad\Controllers\TipoComprobanteController;
use App\Correo\Controllers\ConfiguracionCorreoController;
use App\Correo\Controllers\RecordatorioCorreoController;
use App\Creditos\Controllers\CreditoController;
use App\Dashboard\Controllers\DashboardController;
use App\Egresos\Controllers\EgresoController;
use App\Empleadores\Controllers\EmpleadorController;
use App\Empresas\Controllers\EmpresaAdminController;
use App\Empresas\Controllers\EmpresaController;
use App\Exportacion\Controllers\ExportacionController;
use App\Facturacion\Controllers\CobroFacturacionController;
use App\Facturacion\Controllers\ConsecutivoFacturacionController;
use App\Facturacion\Controllers\DocumentoFacturacionController;
use App\Gastos\Controllers\ActividadEconomicaController;
use App\Gastos\Controllers\GastoController;
use App\Gastos\Controllers\ParametrizacionTipoGastoController;
use App\Gastos\Controllers\ReporteGastosCentroCostoController;
use App\Gastos\Controllers\TarifaReteIcaMunicipioController;
use App\Gastos\Controllers\TipoGastoController;
use App\Gastos\Controllers\ValorUvtController;
use App\Geografia\Controllers\EstadoController;
use App\Geografia\Controllers\MunicipioController;
use App\Geografia\Controllers\PaisController;
use App\Importacion\Controllers\ImportacionController;
use App\Metas\Controllers\AlertaMetaController;
use App\Metas\Controllers\CierreMetaController;
use App\Metas\Controllers\ConfiguracionMetaController;
use App\Metas\Controllers\MetaController;
use App\Notificaciones\Controllers\NotificacionController;
use App\Pagos\Controllers\PagoController;
use App\Proveedores\Controllers\ProveedorController;
use App\Recibos\Controllers\ReciboCajaController;
use App\Respaldos\Controllers\DescargaBaseController;
use App\Terceros\Controllers\TerceroController;
use App\Usuarios\Controllers\PermisoController;
use App\Usuarios\Controllers\RolController;
use App\Usuarios\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'usuarioActual']);
    Route::patch('/me/clave', [AuthController::class, 'cambiarClave']);

    Route::get('/empresas', [EmpresaAdminController::class, 'index'])->middleware('superadmin');
    Route::post('/empresas', [EmpresaAdminController::class, 'store'])->middleware('superadmin');
    Route::get('/empresas/{empresa}', [EmpresaAdminController::class, 'show'])->middleware('superadmin');
    Route::patch('/empresas/{empresa}', [EmpresaAdminController::class, 'update'])->middleware('superadmin');

    Route::get('/empresa', [EmpresaController::class, 'show']);
    Route::patch('/empresa', [EmpresaController::class, 'update'])->middleware('admin');
    Route::post('/empresa/logo', [EmpresaController::class, 'uploadLogo'])->middleware('admin');

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/geografia/paises', [PaisController::class, 'index']);
    Route::get('/geografia/estados', [EstadoController::class, 'index']);
    Route::get('/geografia/municipios', [MunicipioController::class, 'index']);
    Route::get('/geografia/municipios/listado', [MunicipioController::class, 'listado']);
    Route::post('/geografia/municipios', [MunicipioController::class, 'store'])->middleware('superadmin');
    Route::patch('/geografia/municipios/{municipio}', [MunicipioController::class, 'update'])->middleware('superadmin');

    Route::get('/auditoria/bitacora', [BitacoraAuditoriaController::class, 'index'])->middleware('permiso_o_superadmin:auditoria,listar');
    Route::post('/auditoria/bitacora/exportar', [BitacoraAuditoriaController::class, 'exportar'])->middleware('permiso_o_superadmin:auditoria,exportar');
    Route::get('/auditoria/respaldo', [RecuperacionAuditoriaController::class, 'index'])->middleware('superadmin');
    Route::post('/auditoria/respaldo/recuperar', [RecuperacionAuditoriaController::class, 'recuperar'])->middleware('superadmin');

    Route::get('/respaldos/base', [DescargaBaseController::class, 'base'])->middleware('superadmin');
    Route::get('/respaldos/empresa', [DescargaBaseController::class, 'empresa'])->middleware('admin');

    Route::get('/exportaciones', [ExportacionController::class, 'index']);
    Route::get('/exportaciones/{exportacion}/descargar', [ExportacionController::class, 'descargar'])->whereNumber('exportacion');

    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::post('/notificaciones/leer-todas', [NotificacionController::class, 'leerTodas']);
    Route::post('/notificaciones/{notificacion}/leer', [NotificacionController::class, 'leer'])->whereNumber('notificacion');

    Route::get('/usuarios', [UsuarioController::class, 'index'])->middleware('permiso:usuarios,listar');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->middleware('permiso:usuarios,crear');
    Route::get('/usuarios/{usuario}', [UsuarioController::class, 'show'])->middleware('permiso:usuarios,editar');
    Route::patch('/usuarios/{usuario}', [UsuarioController::class, 'update'])->middleware('permiso:usuarios,editar');
    Route::patch('/usuarios/{usuario}/clave', [UsuarioController::class, 'cambiarClave'])->middleware('admin');
    Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->middleware('permiso:usuarios,eliminar');

    Route::get('/roles', [RolController::class, 'index'])->middleware('permiso:usuarios,listar');
    Route::get('/roles/{rol}', [RolController::class, 'show'])->middleware('admin');
    Route::post('/roles', [RolController::class, 'store'])->middleware('admin');
    Route::patch('/roles/{rol}', [RolController::class, 'update'])->middleware('admin');
    Route::delete('/roles/{rol}', [RolController::class, 'destroy'])->middleware('admin');
    Route::get('/permisos', [PermisoController::class, 'index'])->middleware('permiso:usuarios,listar');

    Route::get('/clientes', [ClienteController::class, 'index'])->middleware('permiso:clientes,listar');
    Route::post('/clientes/exportar', [ClienteController::class, 'exportar'])->middleware('permiso:clientes,exportar');
    Route::post('/clientes', [ClienteController::class, 'store'])->middleware('permiso:clientes,crear');
    Route::get('/clientes/asesores-asignables', [ClienteController::class, 'asesoresAsignables'])->middleware('permiso:clientes,crear');
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->middleware('permiso:clientes,ver');
    Route::patch('/clientes/{cliente}', [ClienteController::class, 'update'])->middleware('permiso:clientes,editar');
    Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->middleware('admin');
    Route::patch('/clientes/{cliente}/asesor', [ClienteController::class, 'reasignarAsesor'])->middleware('admin');
    Route::get('/clientes/{cliente}/asesor-historial', [ClienteController::class, 'historialAsesores'])->middleware('admin');
    Route::get('/clientes/{cliente}/resumen', [ClienteController::class, 'resumen'])->middleware('permiso:clientes,ver');
    Route::get('/clientes/{cliente}/documentos', [ClienteController::class, 'documentos'])->middleware('permiso:clientes,ver');
    Route::post('/clientes/{cliente}/documentos', [ClienteController::class, 'subirDocumento'])->middleware('permiso:clientes,editar');

    Route::get('/empleadores', [EmpleadorController::class, 'index'])->middleware('permiso:clientes,listar');
    Route::post('/empleadores', [EmpleadorController::class, 'store'])->middleware('permiso:clientes,crear');
    Route::get('/empleadores/{empleador}', [EmpleadorController::class, 'show'])->middleware('permiso:clientes,ver');
    Route::patch('/empleadores/{empleador}', [EmpleadorController::class, 'update'])->middleware('permiso:clientes,editar');
    Route::delete('/empleadores/{empleador}', [EmpleadorController::class, 'destroy'])->middleware('admin');

    Route::get('/creditos', [CreditoController::class, 'index'])->middleware('permiso:creditos,listar');
    Route::post('/creditos', [CreditoController::class, 'store'])->middleware('permiso:creditos,crear');
    Route::post('/creditos/exportar', [CreditoController::class, 'exportar'])->middleware('permiso:creditos,exportar');
    Route::get('/creditos/{credito}', [CreditoController::class, 'show'])->middleware('permiso:creditos,listar');
    Route::patch('/creditos/{credito}', [CreditoController::class, 'update'])->middleware('permiso:creditos,editar');
    Route::patch('/creditos/{credito}/gastos-administracion', [CreditoController::class, 'actualizarGastosAdministracion'])->middleware('permiso:creditos,editar');
    Route::get('/creditos/{credito}/estado-cuenta', [CreditoController::class, 'estadoCuenta'])->middleware('permiso:creditos,listar');
    Route::post('/creditos/{credito}/estado-cuenta/exportar', [CreditoController::class, 'estadoCuentaExportar'])->middleware('permiso:creditos,exportar');
    Route::get('/creditos/{credito}/estado-cuenta/pdf', [CreditoController::class, 'estadoCuentaPdf'])->middleware('permiso:creditos,imprimir_estado_cuenta');
    Route::post('/creditos/{credito}/aprobar', [CreditoController::class, 'aprobar'])->middleware('permiso:creditos,aprobar');
    Route::post('/creditos/{credito}/desembolsar', [CreditoController::class, 'desembolsar'])->middleware('permiso:creditos,desembolsar');
    Route::post('/creditos/{credito}/cancelar', [CreditoController::class, 'cancelar'])->middleware('permiso:creditos,cancelar');
    Route::delete('/creditos/{credito}', [CreditoController::class, 'destroy'])->middleware('permiso:creditos,eliminar');

    Route::get('/pagos', [PagoController::class, 'listar'])->middleware('permiso:pagos,listar');
    Route::post('/pagos/exportar', [PagoController::class, 'exportar'])->middleware('permiso:pagos,exportar');
    Route::get('/creditos/{credito}/pagos', [PagoController::class, 'index'])->middleware('permiso:pagos,listar');
    Route::get('/recibos', [ReciboCajaController::class, 'listar'])->middleware('permiso:recibos,listar');
    Route::get('/pagos/{pago}/recibo/pdf', [ReciboCajaController::class, 'pdf'])->middleware('permiso:pagos,imprimir_recibo')->whereNumber('pago');
    Route::post('/creditos/{credito}/pagos', [PagoController::class, 'store'])->middleware('permiso:pagos,crear');
    Route::post('/pagos/{pago}/reversar', [PagoController::class, 'reversar'])->middleware('permiso:pagos,reversar')->whereNumber('pago');
    Route::get('/creditos/{credito}/liquidacion', [PagoController::class, 'liquidacion'])->middleware('permiso:pagos,crear');
    Route::post('/creditos/{credito}/liquidacion', [PagoController::class, 'liquidar'])->middleware('permiso:pagos,crear');

    Route::get('/cartera', [CarteraController::class, 'index'])->middleware('permiso:cartera,listar');
    Route::post('/cartera/exportar', [CarteraController::class, 'exportar'])->middleware('permiso:cartera,exportar');
    Route::get('/cartera/reporte-empleador', [CarteraController::class, 'reporteEmpleador'])->middleware('permiso:cartera,listar');
    Route::get('/cartera/reporte-empleador/pdf', [CarteraController::class, 'reporteEmpleadorPdf'])->middleware('permiso:cartera,imprimir_reporte_empleador');
    Route::post('/cartera/reporte-empleador/exportar', [CarteraController::class, 'exportarReporteEmpleador'])->middleware('permiso:cartera,exportar');

    // Correo de cobranza: la configuración (credenciales) es solo de admin; los recordatorios los envía quien tiene cartera.
    Route::prefix('correo')->middleware('admin')->group(function () {
        Route::get('/configuracion', [ConfiguracionCorreoController::class, 'show']);
        Route::put('/configuracion', [ConfiguracionCorreoController::class, 'update']);
        Route::post('/configuracion/prueba', [ConfiguracionCorreoController::class, 'prueba']);
        Route::post('/vista-previa', [ConfiguracionCorreoController::class, 'vistaPrevia']);
    });
    Route::get('/clientes/{cliente}/recordatorios-correo', [RecordatorioCorreoController::class, 'historial'])->middleware('permiso:clientes,ver');
    Route::post('/clientes/{cliente}/recordatorios-correo/vista-previa', [RecordatorioCorreoController::class, 'vistaPrevia'])->middleware('permiso:cartera,enviar_recordatorio');
    Route::post('/clientes/{cliente}/recordatorios-correo', [RecordatorioCorreoController::class, 'enviar'])->middleware('permiso:cartera,enviar_recordatorio');

    // Metas: la lectura admite al asesor solo con la meta consolidada activa (`acceso_metas`); el resto exige su permiso.
    Route::prefix('metas')->group(function () {
        Route::middleware('acceso_metas')->group(function () {
            Route::get('/', [MetaController::class, 'index']);
            Route::get('/alertas', [AlertaMetaController::class, 'index']);
            Route::post('/alertas/{alerta}/vista', [AlertaMetaController::class, 'marcarVista'])->whereNumber('alerta');
        });

        Route::get('/configuracion', [ConfiguracionMetaController::class, 'show'])->middleware('permiso:metas,listar');
        Route::get('/cierres', [CierreMetaController::class, 'index'])->middleware('permiso:metas,listar');

        Route::middleware('permiso:metas,configurar')->group(function () {
            Route::post('/', [MetaController::class, 'store']);
            Route::put('/configuracion', [ConfiguracionMetaController::class, 'update']);
            Route::put('/{meta}', [MetaController::class, 'update'])->whereNumber('meta');
            Route::put('/{meta}/asesores', [MetaController::class, 'distribuir'])->whereNumber('meta');
        });

        Route::post('/cierres/recalcular', [CierreMetaController::class, 'recalcular'])->middleware('permiso:metas,recalcular');
    });

    Route::get('/calendario', [CalendarioController::class, 'index'])->middleware('permiso:calendario,listar');

    Route::get('/contabilidad/plan-cuentas', [PlanCuentaController::class, 'index'])->middleware('permiso:configuracion_contable,listar');
    Route::post('/contabilidad/plan-cuentas', [PlanCuentaController::class, 'store'])->middleware('permiso:configuracion_contable,crear');
    Route::patch('/contabilidad/plan-cuentas/{cuenta}', [PlanCuentaController::class, 'update'])->middleware('permiso:configuracion_contable,editar');

    Route::get('/contabilidad/periodos', [PeriodoContableController::class, 'index'])->middleware('permiso:configuracion_contable,listar');
    Route::post('/contabilidad/periodos/{periodo}/cerrar', [PeriodoContableController::class, 'cerrar'])->middleware('permiso:configuracion_contable,cerrar_periodo');
    Route::post('/contabilidad/periodos/{periodo}/reabrir', [PeriodoContableController::class, 'reabrir'])->middleware('permiso:configuracion_contable,reabrir_periodo');

    Route::get('/contabilidad/solicitudes-reapertura', [SolicitudReaperturaPeriodoController::class, 'index'])->middleware('permiso:configuracion_contable,listar');
    Route::post('/contabilidad/periodos/{periodo}/solicitar-reapertura', [SolicitudReaperturaPeriodoController::class, 'store'])->middleware('permiso:configuracion_contable,cerrar_periodo');
    Route::post('/contabilidad/solicitudes-reapertura/{solicitud}/autorizar', [SolicitudReaperturaPeriodoController::class, 'autorizar'])->middleware('permiso:configuracion_contable,reabrir_periodo');
    Route::post('/contabilidad/solicitudes-reapertura/{solicitud}/rechazar', [SolicitudReaperturaPeriodoController::class, 'rechazar'])->middleware('permiso:configuracion_contable,reabrir_periodo');

    Route::get('/contabilidad/tipos-comprobante', [TipoComprobanteController::class, 'index'])->middleware('permiso:configuracion_contable,listar');
    Route::post('/contabilidad/tipos-comprobante', [TipoComprobanteController::class, 'store'])->middleware('permiso:configuracion_contable,crear');

    Route::get('/contabilidad/centros-costo', [CentroCostoController::class, 'index'])->middleware('permiso:configuracion_contable,listar');
    Route::post('/contabilidad/centros-costo', [CentroCostoController::class, 'store'])->middleware('permiso:configuracion_contable,crear');
    Route::patch('/contabilidad/centros-costo/{centro}', [CentroCostoController::class, 'update'])->middleware('permiso:configuracion_contable,editar');

    Route::get('/contabilidad/reglas', [ReglaContabilizacionController::class, 'index'])->middleware('permiso:configuracion_contable,configurar');
    Route::post('/contabilidad/reglas', [ReglaContabilizacionController::class, 'store'])->middleware('permiso:configuracion_contable,configurar');

    Route::get('/contabilidad/comprobantes', [ComprobanteController::class, 'index'])->middleware('permiso:contabilidad,listar');
    Route::post('/contabilidad/comprobantes', [ComprobanteController::class, 'store'])->middleware('permiso:contabilidad,crear_manual');
    Route::get('/contabilidad/comprobantes/{comprobante}', [ComprobanteController::class, 'show'])->middleware('permiso:contabilidad,listar');
    Route::get('/contabilidad/comprobantes/{comprobante}/pdf', [ComprobanteController::class, 'pdf'])->middleware('permiso:contabilidad,imprimir_comprobante')->whereNumber('comprobante');
    Route::post('/contabilidad/comprobantes/{comprobante}/revisar', [ComprobanteController::class, 'revisar'])->middleware('permiso:contabilidad,revisar')->whereNumber('comprobante');
    Route::post('/contabilidad/comprobantes/{comprobante}/contabilizar', [ComprobanteController::class, 'contabilizar'])->middleware('permiso:contabilidad,contabilizar')->whereNumber('comprobante');
    Route::post('/contabilidad/comprobantes/{comprobante}/reversar', [ComprobanteController::class, 'reversar'])->middleware('permiso:contabilidad,anular');

    Route::get('/contabilidad/libro-diario', [ReporteContableController::class, 'libroDiario'])->middleware('permiso:informes_contables,listar');
    Route::get('/contabilidad/libro-mayor', [ReporteContableController::class, 'libroMayor'])->middleware('permiso:informes_contables,listar');
    Route::get('/contabilidad/balance-prueba', [ReporteContableController::class, 'balancePrueba'])->middleware('permiso:informes_contables,listar');
    Route::get('/contabilidad/estados-financieros', [ReporteContableController::class, 'estadosFinancieros'])->middleware('permiso:informes_contables,listar');
    Route::post('/contabilidad/libro-diario/exportar', [ReporteContableController::class, 'exportarLibroDiario'])->middleware('permiso:informes_contables,exportar');
    Route::post('/contabilidad/libro-mayor/exportar', [ReporteContableController::class, 'exportarLibroMayor'])->middleware('permiso:informes_contables,exportar');
    Route::post('/contabilidad/balance-prueba/exportar', [ReporteContableController::class, 'exportarBalancePrueba'])->middleware('permiso:informes_contables,exportar');
    Route::post('/contabilidad/estados-financieros/exportar', [ReporteContableController::class, 'exportarEstadosFinancieros'])->middleware('permiso:informes_contables,exportar');

    Route::get('/contabilidad/libro-diario/pdf', [ReporteContableController::class, 'pdfLibroDiario'])->middleware('permiso:informes_contables,imprimir');
    Route::get('/contabilidad/libro-mayor/pdf', [ReporteContableController::class, 'pdfLibroMayor'])->middleware('permiso:informes_contables,imprimir');
    Route::get('/contabilidad/balance-prueba/pdf', [ReporteContableController::class, 'pdfBalancePrueba'])->middleware('permiso:informes_contables,imprimir');
    Route::get('/contabilidad/estados-financieros/pdf', [ReporteContableController::class, 'pdfEstadosFinancieros'])->middleware('permiso:informes_contables,imprimir');
    Route::get('/contabilidad/conciliacion-apertura', [ReporteContableController::class, 'conciliacionApertura'])->middleware('permiso:informes_contables,listar');

    Route::get('/contabilidad/auxiliares', [AuxiliarContableController::class, 'index'])->middleware('permiso:informes_contables,listar');
    Route::post('/contabilidad/auxiliares/exportar', [AuxiliarContableController::class, 'exportar'])->middleware('permiso:informes_contables,exportar');
    Route::get('/contabilidad/auxiliares/pdf', [AuxiliarContableController::class, 'pdf'])->middleware('permiso:informes_contables,imprimir');

    Route::get('/importaciones', [ImportacionController::class, 'index'])->middleware('permiso:importaciones,listar');
    Route::post('/importaciones', [ImportacionController::class, 'store'])->middleware('permiso:importaciones,ejecutar');
    Route::get('/importaciones/guias/{tipo}', [ImportacionController::class, 'guia'])->middleware('permiso:importaciones,ejecutar')->where('tipo', '[a-z_]+');
    Route::get('/importaciones/{importacion}', [ImportacionController::class, 'show'])->middleware('permiso:importaciones,listar')->whereNumber('importacion');
    Route::get('/importaciones/{importacion}/errores', [ImportacionController::class, 'errores'])->middleware('permiso:importaciones,listar')->whereNumber('importacion');
    Route::get('/importaciones/{importacion}/reporte', [ImportacionController::class, 'reporte'])->middleware('permiso:importaciones,listar')->whereNumber('importacion');
    Route::post('/importaciones/{importacion}/decisiones', [ImportacionController::class, 'decidir'])->middleware('permiso:importaciones,ejecutar')->whereNumber('importacion');
    Route::post('/importaciones/{importacion}/confirmar', [ImportacionController::class, 'confirmar'])->middleware('permiso:importaciones,ejecutar')->whereNumber('importacion');
    Route::post('/importaciones/{importacion}/cancelar', [ImportacionController::class, 'cancelar'])->middleware('permiso:importaciones,ejecutar')->whereNumber('importacion');

    Route::get('/terceros', [TerceroController::class, 'index']);
    Route::post('/terceros/personas-naturales', [TerceroController::class, 'storeNatural'])->middleware('permiso:terceros,crear');
    Route::post('/terceros/personas-juridicas', [TerceroController::class, 'storeJuridica'])->middleware('permiso:terceros,crear');
    Route::get('/terceros/{tercero}', [TerceroController::class, 'show'])->middleware('permiso:terceros,listar');
    Route::patch('/terceros/personas-naturales/{tercero}', [TerceroController::class, 'updateNatural'])->middleware('permiso:terceros,editar');
    Route::patch('/terceros/personas-juridicas/{tercero}', [TerceroController::class, 'updateJuridica'])->middleware('permiso:terceros,editar');
    Route::get('/terceros/{tercero}/documentos-pendientes', [DocumentoTerceroController::class, 'pendientes'])->middleware('permiso:contabilidad,listar');

    Route::get('/proveedores', [ProveedorController::class, 'index'])->middleware('permiso:terceros,listar');
    Route::post('/proveedores/personas-naturales', [ProveedorController::class, 'storeNatural'])->middleware('permiso:terceros,crear');
    Route::post('/proveedores/personas-juridicas', [ProveedorController::class, 'storeJuridica'])->middleware('permiso:terceros,crear');
    Route::get('/proveedores/{proveedor}', [ProveedorController::class, 'show'])->middleware('permiso:terceros,listar');
    Route::patch('/proveedores/personas-naturales/{proveedor}', [ProveedorController::class, 'updateNatural'])->middleware('permiso:terceros,editar');
    Route::patch('/proveedores/personas-juridicas/{proveedor}', [ProveedorController::class, 'updateJuridica'])->middleware('permiso:terceros,editar');

    Route::get('/gastos/tipos-gasto', [TipoGastoController::class, 'index'])->middleware('permiso:gastos,listar');
    Route::post('/gastos/tipos-gasto', [TipoGastoController::class, 'store'])->middleware('permiso:gastos,parametrizar');
    Route::patch('/gastos/tipos-gasto/{tipoGasto}', [TipoGastoController::class, 'update'])->middleware('permiso:gastos,parametrizar');

    Route::get('/gastos/actividades-economicas', [ActividadEconomicaController::class, 'index'])->middleware('permiso:tributario,listar');
    Route::post('/gastos/actividades-economicas', [ActividadEconomicaController::class, 'store'])->middleware('permiso:tributario,parametrizar');
    Route::patch('/gastos/actividades-economicas/{actividadEconomica}', [ActividadEconomicaController::class, 'update'])->middleware('permiso:tributario,parametrizar');

    Route::get('/gastos/valores-uvt', [ValorUvtController::class, 'index'])->middleware('permiso:tributario,listar');
    Route::post('/gastos/valores-uvt', [ValorUvtController::class, 'store'])->middleware('permiso:tributario,parametrizar');

    Route::get('/gastos/parametrizaciones', [ParametrizacionTipoGastoController::class, 'index'])->middleware('permiso:gastos,listar');
    Route::post('/gastos/parametrizaciones', [ParametrizacionTipoGastoController::class, 'store'])->middleware('permiso:gastos,parametrizar');

    Route::get('/gastos/tarifas-reteica', [TarifaReteIcaMunicipioController::class, 'index'])->middleware('permiso:tributario,listar');
    Route::post('/gastos/tarifas-reteica', [TarifaReteIcaMunicipioController::class, 'store'])->middleware('permiso:tributario,parametrizar');

    Route::get('/gastos', [GastoController::class, 'index'])->middleware('permiso:gastos,listar');
    Route::post('/gastos/exportar', [GastoController::class, 'exportar'])->middleware('permiso:gastos,exportar');
    Route::get('/gastos/reporte-centro-costo', [ReporteGastosCentroCostoController::class, 'index'])->middleware('permiso:gastos,listar');
    Route::post('/gastos/reporte-centro-costo/exportar', [ReporteGastosCentroCostoController::class, 'exportar'])->middleware('permiso:gastos,exportar');

    Route::get('/facturacion/documentos', [DocumentoFacturacionController::class, 'index'])->middleware('permiso:facturacion,listar');
    Route::post('/facturacion/documentos', [DocumentoFacturacionController::class, 'store'])->middleware('permiso:facturacion,crear');
    Route::get('/facturacion/documentos/{documento}', [DocumentoFacturacionController::class, 'show'])->middleware('permiso:facturacion,listar');
    Route::patch('/facturacion/documentos/{documento}', [DocumentoFacturacionController::class, 'update'])->middleware('permiso:facturacion,crear');
    Route::delete('/facturacion/documentos/{documento}', [DocumentoFacturacionController::class, 'destroy'])->middleware('permiso:facturacion,crear');
    Route::post('/facturacion/documentos/{documento}/emitir', [DocumentoFacturacionController::class, 'emitir'])->middleware('permiso:facturacion,crear');
    Route::post('/facturacion/documentos/{documento}/notas', [DocumentoFacturacionController::class, 'nota'])->middleware('permiso:facturacion,anular');
    Route::post('/facturacion/documentos/{documento}/anular', [DocumentoFacturacionController::class, 'anular'])->middleware('permiso:facturacion,anular');
    Route::post('/facturacion/documentos/{documento}/cobros', [CobroFacturacionController::class, 'store'])->middleware('permiso:facturacion,crear');
    Route::post('/facturacion/cobros/{cobro}/reversar', [CobroFacturacionController::class, 'reversar'])->middleware('permiso:facturacion,anular');
    Route::get('/facturacion/documentos/{documento}/pdf', [DocumentoFacturacionController::class, 'pdf'])->middleware('permiso:facturacion,imprimir');
    Route::get('/facturacion/consecutivos', [ConsecutivoFacturacionController::class, 'index'])->middleware('permiso:facturacion,listar');
    Route::patch('/facturacion/consecutivos/{tipo}', [ConsecutivoFacturacionController::class, 'update'])->middleware('permiso:facturacion,configurar');
    Route::post('/gastos', [GastoController::class, 'store'])->middleware('permiso:gastos,crear');
    Route::get('/gastos/{gasto}', [GastoController::class, 'show'])->middleware('permiso:gastos,listar');
    Route::delete('/gastos/{gasto}', [GastoController::class, 'destroy'])->middleware('permiso:gastos,anular');

    Route::get('/egresos', [EgresoController::class, 'index'])->middleware('permiso:egresos,listar');
    Route::post('/egresos', [EgresoController::class, 'store'])->middleware('permiso:egresos,crear');
});
