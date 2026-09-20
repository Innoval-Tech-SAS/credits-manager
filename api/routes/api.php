<?php

use App\Auth\Controllers\AuthController;
use App\Calendario\Controllers\CalendarioController;
use App\Cartera\Controllers\CarteraController;
use App\Clientes\Controllers\ClienteController;
use App\Contabilidad\Controllers\CentroCostoController;
use App\Contabilidad\Controllers\ComprobanteController;
use App\Contabilidad\Controllers\DocumentoTerceroController;
use App\Contabilidad\Controllers\MigracionAperturaController;
use App\Contabilidad\Controllers\PeriodoContableController;
use App\Contabilidad\Controllers\PlanCuentaController;
use App\Contabilidad\Controllers\ReglaContabilizacionController;
use App\Contabilidad\Controllers\ReporteContableController;
use App\Contabilidad\Controllers\TipoComprobanteController;
use App\Creditos\Controllers\CreditoController;
use App\Dashboard\Controllers\DashboardController;
use App\Empleadores\Controllers\EmpleadorController;
use App\Empresas\Controllers\EmpresaAdminController;
use App\Empresas\Controllers\EmpresaController;
use App\Gastos\Controllers\ActividadEconomicaController;
use App\Gastos\Controllers\GastoController;
use App\Gastos\Controllers\MunicipioController;
use App\Gastos\Controllers\ParametrizacionTipoGastoController;
use App\Gastos\Controllers\TarifaReteIcaMunicipioController;
use App\Gastos\Controllers\TipoGastoController;
use App\Gastos\Controllers\ValorUvtController;
use App\Pagos\Controllers\PagoController;
use App\Proveedores\Controllers\ProveedorController;
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
    Route::post('/clientes', [ClienteController::class, 'store'])->middleware('permiso:clientes,crear');
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->middleware('permiso:clientes,editar');
    Route::patch('/clientes/{cliente}', [ClienteController::class, 'update'])->middleware('permiso:clientes,editar');
    Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->middleware('admin');
    Route::get('/clientes/{cliente}/resumen', [ClienteController::class, 'resumen'])->middleware('permiso:clientes,editar');
    Route::get('/clientes/{cliente}/documentos', [ClienteController::class, 'documentos'])->middleware('permiso:clientes,editar');
    Route::post('/clientes/{cliente}/documentos', [ClienteController::class, 'subirDocumento'])->middleware('permiso:clientes,editar');

    Route::get('/empleadores', [EmpleadorController::class, 'index'])->middleware('permiso:clientes,listar');
    Route::post('/empleadores', [EmpleadorController::class, 'store'])->middleware('permiso:clientes,crear');
    Route::get('/empleadores/{empleador}', [EmpleadorController::class, 'show'])->middleware('permiso:clientes,editar');
    Route::patch('/empleadores/{empleador}', [EmpleadorController::class, 'update'])->middleware('permiso:clientes,editar');
    Route::delete('/empleadores/{empleador}', [EmpleadorController::class, 'destroy'])->middleware('admin');

    Route::get('/creditos', [CreditoController::class, 'index'])->middleware('permiso:creditos,listar');
    Route::post('/creditos', [CreditoController::class, 'store'])->middleware('permiso:creditos,crear');
    Route::get('/creditos/{credito}', [CreditoController::class, 'show'])->middleware('permiso:creditos,listar');
    Route::get('/creditos/{credito}/estado-cuenta', [CreditoController::class, 'estadoCuenta'])->middleware('permiso:creditos,listar');
    Route::post('/creditos/{credito}/aprobar', [CreditoController::class, 'aprobar'])->middleware('permiso:creditos,aprobar');
    Route::post('/creditos/{credito}/desembolsar', [CreditoController::class, 'desembolsar'])->middleware('permiso:creditos,desembolsar');
    Route::post('/creditos/{credito}/cancelar', [CreditoController::class, 'cancelar'])->middleware('permiso:creditos,cancelar');
    Route::delete('/creditos/{credito}', [CreditoController::class, 'destroy'])->middleware('permiso:creditos,eliminar');

    Route::get('/pagos', [PagoController::class, 'listar'])->middleware('permiso:pagos,listar');
    Route::get('/creditos/{credito}/pagos', [PagoController::class, 'index'])->middleware('permiso:pagos,listar');
    Route::post('/creditos/{credito}/pagos', [PagoController::class, 'store'])->middleware('permiso:pagos,crear');
    Route::get('/creditos/{credito}/liquidacion', [PagoController::class, 'liquidacion'])->middleware('permiso:pagos,crear');
    Route::post('/creditos/{credito}/liquidacion', [PagoController::class, 'liquidar'])->middleware('permiso:pagos,crear');

    Route::get('/cartera', [CarteraController::class, 'index'])->middleware('permiso:cartera,listar');
    Route::get('/cartera/reporte-empleador', [CarteraController::class, 'reporteEmpleador'])->middleware('permiso:cartera,listar');

    Route::get('/calendario', [CalendarioController::class, 'index'])->middleware('permiso:calendario,listar');

    Route::get('/contabilidad/plan-cuentas', [PlanCuentaController::class, 'index'])->middleware('permiso:contabilidad,listar');
    Route::post('/contabilidad/plan-cuentas', [PlanCuentaController::class, 'store'])->middleware('permiso:contabilidad,crear');
    Route::patch('/contabilidad/plan-cuentas/{cuenta}', [PlanCuentaController::class, 'update'])->middleware('permiso:contabilidad,editar');

    Route::get('/contabilidad/periodos', [PeriodoContableController::class, 'index'])->middleware('permiso:contabilidad,listar');
    Route::post('/contabilidad/periodos/{periodo}/cerrar', [PeriodoContableController::class, 'cerrar'])->middleware('permiso:contabilidad,cerrar_periodo');
    Route::post('/contabilidad/periodos/{periodo}/reabrir', [PeriodoContableController::class, 'reabrir'])->middleware('permiso:contabilidad,reabrir_periodo');

    Route::get('/contabilidad/tipos-comprobante', [TipoComprobanteController::class, 'index'])->middleware('permiso:contabilidad,listar');
    Route::post('/contabilidad/tipos-comprobante', [TipoComprobanteController::class, 'store'])->middleware('permiso:contabilidad,crear');

    Route::get('/contabilidad/centros-costo', [CentroCostoController::class, 'index'])->middleware('permiso:contabilidad,listar');
    Route::post('/contabilidad/centros-costo', [CentroCostoController::class, 'store'])->middleware('permiso:contabilidad,crear');
    Route::patch('/contabilidad/centros-costo/{centro}', [CentroCostoController::class, 'update'])->middleware('permiso:contabilidad,editar');

    Route::get('/contabilidad/reglas', [ReglaContabilizacionController::class, 'index'])->middleware('permiso:contabilidad,configurar');
    Route::post('/contabilidad/reglas', [ReglaContabilizacionController::class, 'store'])->middleware('permiso:contabilidad,configurar');

    Route::get('/contabilidad/comprobantes', [ComprobanteController::class, 'index'])->middleware('permiso:contabilidad,listar');
    Route::post('/contabilidad/comprobantes', [ComprobanteController::class, 'store'])->middleware('permiso:contabilidad,crear_manual');
    Route::get('/contabilidad/comprobantes/{comprobante}', [ComprobanteController::class, 'show'])->middleware('permiso:contabilidad,listar');
    Route::post('/contabilidad/comprobantes/{comprobante}/reversar', [ComprobanteController::class, 'reversar'])->middleware('permiso:contabilidad,anular');

    Route::get('/contabilidad/libro-diario', [ReporteContableController::class, 'libroDiario'])->middleware('permiso:contabilidad,listar');
    Route::get('/contabilidad/libro-mayor', [ReporteContableController::class, 'libroMayor'])->middleware('permiso:contabilidad,listar');
    Route::get('/contabilidad/balance-prueba', [ReporteContableController::class, 'balancePrueba'])->middleware('permiso:contabilidad,listar');
    Route::get('/contabilidad/estados-financieros', [ReporteContableController::class, 'estadosFinancieros'])->middleware('permiso:contabilidad,listar');

    Route::get('/contabilidad/migraciones-apertura', [MigracionAperturaController::class, 'index'])->middleware('permiso:contabilidad,listar');
    Route::post('/contabilidad/migraciones-apertura', [MigracionAperturaController::class, 'store'])->middleware('admin');

    Route::get('/terceros', [TerceroController::class, 'index']);
    Route::post('/terceros', [TerceroController::class, 'store'])->middleware('permiso:contabilidad,crear');
    Route::get('/terceros/{tercero}', [TerceroController::class, 'show'])->middleware('permiso:contabilidad,listar');
    Route::patch('/terceros/{tercero}', [TerceroController::class, 'update'])->middleware('permiso:contabilidad,editar');
    Route::get('/terceros/{tercero}/documentos-pendientes', [DocumentoTerceroController::class, 'pendientes'])->middleware('permiso:contabilidad,listar');

    Route::get('/proveedores', [ProveedorController::class, 'index'])->middleware('permiso:contabilidad,listar');
    Route::post('/proveedores', [ProveedorController::class, 'store'])->middleware('permiso:contabilidad,crear');
    Route::get('/proveedores/{proveedor}', [ProveedorController::class, 'show'])->middleware('permiso:contabilidad,listar');
    Route::patch('/proveedores/{proveedor}', [ProveedorController::class, 'update'])->middleware('permiso:contabilidad,editar');

    Route::get('/gastos/tipos-gasto', [TipoGastoController::class, 'index'])->middleware('permiso:gastos,listar');
    Route::post('/gastos/tipos-gasto', [TipoGastoController::class, 'store'])->middleware('permiso:gastos,parametrizar');
    Route::patch('/gastos/tipos-gasto/{tipoGasto}', [TipoGastoController::class, 'update'])->middleware('permiso:gastos,parametrizar');

    Route::get('/gastos/municipios', [MunicipioController::class, 'index'])->middleware('permiso:gastos,listar');
    Route::post('/gastos/municipios', [MunicipioController::class, 'store'])->middleware('permiso:gastos,parametrizar');
    Route::patch('/gastos/municipios/{municipio}', [MunicipioController::class, 'update'])->middleware('permiso:gastos,parametrizar');

    Route::get('/gastos/actividades-economicas', [ActividadEconomicaController::class, 'index'])->middleware('permiso:gastos,listar');
    Route::post('/gastos/actividades-economicas', [ActividadEconomicaController::class, 'store'])->middleware('permiso:gastos,parametrizar');
    Route::patch('/gastos/actividades-economicas/{actividadEconomica}', [ActividadEconomicaController::class, 'update'])->middleware('permiso:gastos,parametrizar');

    Route::get('/gastos/valores-uvt', [ValorUvtController::class, 'index'])->middleware('permiso:gastos,listar');
    Route::post('/gastos/valores-uvt', [ValorUvtController::class, 'store'])->middleware('permiso:gastos,parametrizar');

    Route::get('/gastos/parametrizaciones', [ParametrizacionTipoGastoController::class, 'index'])->middleware('permiso:gastos,listar');
    Route::post('/gastos/parametrizaciones', [ParametrizacionTipoGastoController::class, 'store'])->middleware('permiso:gastos,parametrizar');

    Route::get('/gastos/tarifas-reteica', [TarifaReteIcaMunicipioController::class, 'index'])->middleware('permiso:gastos,listar');
    Route::post('/gastos/tarifas-reteica', [TarifaReteIcaMunicipioController::class, 'store'])->middleware('permiso:gastos,parametrizar');

    Route::get('/gastos', [GastoController::class, 'index'])->middleware('permiso:gastos,listar');
    Route::post('/gastos', [GastoController::class, 'store'])->middleware('permiso:gastos,crear');
    Route::get('/gastos/{gasto}', [GastoController::class, 'show'])->middleware('permiso:gastos,listar');
    Route::delete('/gastos/{gasto}', [GastoController::class, 'destroy'])->middleware('permiso:gastos,anular');
});
