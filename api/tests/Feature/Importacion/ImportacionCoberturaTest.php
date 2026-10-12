<?php

namespace Tests\Feature\Importacion;

use App\Contabilidad\Enums\EstadoPeriodo;
use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\PeriodoContable;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Services\ComprobanteService;
use App\Core\Dinero\Dinero;
use App\Creditos\Models\Credito;
use App\Creditos\Models\Cuota;
use App\Empresas\Models\Empresa;
use App\Importacion\Data\ContextoEjecucion;
use App\Importacion\Data\FilaRechazada;
use App\Importacion\Enums\EstadoImportacion;
use App\Importacion\Enums\TipoImportacion;
use App\Importacion\Jobs\EscribirImportacionJob;
use App\Importacion\Jobs\ValidarImportacionJob;
use App\Importacion\Lectura\ConvertidorValor;
use App\Importacion\Lectura\LectorCsv;
use App\Importacion\Models\ImportacionError;
use App\Importacion\Procesadores\ProcesadorFila;
use App\Importacion\Procesadores\ProcesadorFilaFactory;
use App\Importacion\Repositories\DatosImportacionRepository;
use App\Importacion\Services\ImportacionEjecutorService;
use App\Importacion\Services\ImportacionService;
use App\Pagos\Services\PagoService;
use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\ImportaArchivos;
use Tests\TestCase;

/** Caminos de error, bordes de validación y ramas que las suites de flujo no recorren. Datos falsos. */
class ImportacionCoberturaTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use ImportaArchivos;
    use RefreshDatabase;

    private const CLIENTES = "Cédula;Nombre completo;Teléfono;Correo electrónico;Empresa (nómina);Dirección;Barrio;Ciudad;Fecha de vínculo (si se conoce);Observaciones\n";

    private const CREDITOS = "Código de crédito;Cédula cliente;Fecha de desembolso;Valor desembolsado (capital);Tasa de interés mensual (%);Plazo (# cuotas);Periodicidad (Mensual/Quincenal);Cuota fija (si ya la calcularon);¿Es refinanciación? (Sí/No);Código del crédito anterior (si refinancia);Saldo refinanciado;Estado actual;Observaciones\n";

    private const PLAN = "Código de crédito;Número de cuota;Fecha de vencimiento;Valor capital;Valor interés;Valor total cuota;Estado (Pendiente/Pagada/Vencida)\n";

    private const PAGOS = "Código de crédito;Fecha de pago;Valor pagado;Tipo (Cuota completa/Abono parcial);Número de cuota a la que aplica (si se sabe);Cuenta/caja receptora;Registrado por;Observaciones\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararImportaciones();
    }

    /** @param array<int, string> $campos */
    private function linea(array $campos): string
    {
        return implode(';', $campos)."\n";
    }

    private function cliente(string $cedula = '1001'): void
    {
        $this->importarCompleto('clientes', self::CLIENTES."{$cedula};Ana Pérez;;;;;;;;\n");
    }

    private function credito(string $codigo = 'CR-1'): void
    {
        $this->importarCompleto('creditos', self::CREDITOS.$this->linea([$codigo, '1001', '01/10/2023', '2000000', '2,5', '4', 'mensual', '', 'No', '', '', '', '']));
    }

    private function reglasDeDesembolso(): PlanCuenta
    {
        $cartera = $this->crearCuenta(['codigo' => '133005', 'nombre' => 'Cartera']);
        $caja = $this->crearCuenta(['codigo' => '110505', 'nombre' => 'Caja']);
        $contrapartida = $this->crearCuenta(['codigo' => '390505', 'nombre' => 'Apertura']);
        $this->crearRegla(EventoContable::DesembolsoCredito, $cartera, $caja, $this->crearTipoComprobante());

        return $contrapartida;
    }

    private function reglasDeRecaudo(): void
    {
        $caja = $this->crearCuenta(['codigo' => '110505', 'nombre' => 'Caja']);
        $cartera = $this->crearCuenta(['codigo' => '133005', 'nombre' => 'Cartera']);
        $ingreso = $this->crearCuenta(['codigo' => '421005', 'nombre' => 'Intereses']);
        $tipo = $this->crearTipoComprobante();
        $this->crearRegla(EventoContable::RecaudoCapital, $caja, $cartera, $tipo);
        $this->crearRegla(EventoContable::RecaudoInteres, $caja, $ingreso, $tipo);
    }

    private function valorDeCuota(string $codigo, int $numero): string
    {
        return Cuota::query()->whereHas('credito', fn ($q) => $q->where('codigo', $codigo))->where('numero_cuota', $numero)->value('valor')->aDecimalString();
    }

    private function motivos(int $id): string
    {
        return implode(' | ', array_column($this->erroresDe($id), 'motivo'));
    }

    public function test_el_job_de_validacion_ejecuta_la_validacion_y_su_fallo_marca_la_importacion(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n");

        (new ValidarImportacionJob($id, $this->importacion($id)->empresa_id))->handle(app(ImportacionEjecutorService::class));
        $this->assertSame(EstadoImportacion::PendienteDecision, $this->importacion($id)->estado);

        $otra = $this->subirArchivo('clientes', self::CLIENTES."2002;Luis Ramírez;;;;;;;;\n");
        (new ValidarImportacionJob($otra, $this->importacion($otra)->empresa_id))->failed(new RuntimeException('timeout'));

        $this->assertSame(EstadoImportacion::Fallida, $this->importacion($otra)->estado);
        $this->assertDatabaseHas('notificaciones', ['tipo' => 'importacion_fallida']);
    }

    public function test_el_job_de_escritura_escribe_lo_aceptado_y_se_puede_encolar(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n");
        $this->ejecutarValidacion($id);
        $this->postJson("/api/v1/importaciones/{$id}/confirmar")->assertStatus(202);

        (new EscribirImportacionJob($id, $this->importacion($id)->empresa_id))->handle(app(ImportacionEjecutorService::class));

        $this->assertSame(EstadoImportacion::Completada, $this->importacion($id)->estado);
        $this->assertDatabaseCount('clientes', 1);
    }

    public function test_validar_o_escribir_fuera_de_su_estado_no_hace_nada(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n");
        $this->ejecutarValidacion($id);

        $estado = $this->importacion($id)->estado;
        app(ImportacionEjecutorService::class)->validar($id, $this->importacion($id)->empresa_id);
        app(ImportacionEjecutorService::class)->escribir($id, $this->importacion($id)->empresa_id);

        $this->assertSame($estado, $this->importacion($id)->estado);
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_si_el_archivo_desaparece_antes_de_validar_la_importacion_falla_con_un_mensaje_generico(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n");
        Storage::disk('importaciones')->delete($this->importacion($id)->getRawOriginal('archivo_ruta'));

        $this->ejecutarValidacion($id);

        $importacion = $this->importacion($id);
        $this->assertSame(EstadoImportacion::Fallida, $importacion->estado);
        $this->assertSame('No se pudo procesar el archivo por un error inesperado.', $importacion->error);
        $this->assertDatabaseHas('notificaciones', ['tipo' => 'importacion_fallida']);
    }

    public function test_marcar_fallida_no_cambia_una_importacion_ya_completada(): void
    {
        $this->actingAsAdmin();
        $id = $this->importarCompleto('clientes', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n");

        app(ImportacionEjecutorService::class)->marcarFallida($id, $this->importacion($id)->empresa_id, new RuntimeException('tarde'));

        $this->assertSame(EstadoImportacion::Completada, $this->importacion($id)->estado);
    }

    public function test_un_error_inesperado_en_una_fila_rechaza_solo_esa_fila(): void
    {
        $this->actingAsAdmin();
        $this->app->instance(ProcesadorFilaFactory::class, new class extends ProcesadorFilaFactory
        {
            public function para(TipoImportacion $tipo): ProcesadorFila
            {
                return new class implements ProcesadorFila
                {
                    public function precargar(array $valoresLote, ContextoEjecucion $ctx): void {}

                    public function procesar(array $valores, ContextoEjecucion $ctx): void
                    {
                        if ($valores['cedula'] === '1002') {
                            throw new RuntimeException('falla interna con detalle que no debe salir');
                        }
                    }
                };
            }
        });

        $id = $this->importarCompleto('clientes', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n1002;Luis Ramírez;;;;;;;;\n");

        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);
        $this->assertSame(1, $this->importacion($id)->filas_rechazadas);
        $this->assertSame('No se pudo procesar la fila por un error inesperado.', $this->erroresDe($id)[0]['motivo']);
        $this->assertStringNotContainsString('falla interna', $this->motivos($id));
    }

    public function test_miles_de_errores_se_guardan_por_lotes_y_se_cuentan_todos(): void
    {
        $this->actingAsAdmin();
        $filas = str_repeat(";Sin cédula Apellido\n", 510);

        $id = $this->importarCompleto('clientes', self::CLIENTES.$filas);

        $this->assertSame(510, $this->importacion($id)->filas_rechazadas);
        $this->assertSame(510, ImportacionError::query()->where('importacion_id', $id)->count());
    }

    public function test_si_el_asiento_no_se_puede_generar_la_carga_termina_y_lo_informa(): void
    {
        $admin = $this->actingAsAdmin();
        $this->cliente();
        $contrapartida = $this->reglasDeDesembolso();
        PeriodoContable::create(['anio' => now()->year, 'mes' => now()->month, 'estado' => EstadoPeriodo::Cerrado]);

        $id = $this->importarCompleto(
            'creditos',
            self::CREDITOS.$this->linea(['CR-1', '1001', '01/10/2023', '2000000', '2,5', '4', 'mensual', '', 'No', '', '', '', '']),
            ['generar_asiento' => true],
            'creditos.csv',
            ['generar_asiento' => true, 'cuenta_contrapartida_id' => $contrapartida->id],
        );

        $importacion = $this->importacion($id);
        $this->assertSame(EstadoImportacion::Completada, $importacion->estado);
        $this->assertSame(1, $importacion->filas_aceptadas);
        $this->assertNull($importacion->comprobante_id);
        $this->assertStringContainsString('El asiento de apertura no se generó', $this->motivos($id));
        $this->assertDatabaseCount('creditos', 1);
        $this->assertSame($admin->empresa_id, $importacion->empresa_id);
    }

    public function test_el_listado_de_errores_es_paginado_y_viene_de_su_importacion(): void
    {
        $admin = $this->actingAsAdmin();
        $id = $this->importarCompleto('clientes', self::CLIENTES.";Sin cédula\n;Otra\n1003;Carlos\n");

        $respuesta = $this->getJson("/api/v1/importaciones/{$id}/errores?per_page=2")->assertOk();

        $this->assertSame(3, $respuesta->json('total'));
        $this->assertCount(2, $respuesta->json('data'));
        $this->assertSame(2, $respuesta->json('data.0.fila'));
        $this->assertSame($admin->empresa_id, $this->importacion($id)->empresa_id);
    }

    public function test_el_detalle_muestra_las_decisiones_que_faltan(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::CLIENTES."1001;Ana Pérez;;;Empresa Vivo;;;;;\n");
        $this->ejecutarValidacion($id);

        $this->getJson("/api/v1/importaciones/{$id}")
            ->assertOk()
            ->assertJsonPath('importacion.estado', 'pendiente_decision')
            ->assertJsonPath('decisiones.requiere_decision_empleadores', true)
            ->assertJsonPath('decisiones.requiere_decision_asiento', false)
            ->assertJsonPath('decisiones.puede_contabilizar', false);
    }

    public function test_la_decision_de_asiento_exige_el_permiso_de_contabilizar_y_se_puede_rechazar_sin_el(): void
    {
        $admin = $this->actingAsAdmin();
        $this->cliente();
        $this->reglasDeRecaudo();
        $this->credito();
        $valor = $this->valorDeCuota('CR-1', 1);
        $id = $this->subirArchivo('pagos', self::PAGOS."CR-1;14/11/2023;{$valor};Cuota completa;;;;\n");
        $this->ejecutarValidacion($id);

        $asesor = $this->asesorDeLaEmpresa($admin);
        $asesor->permisosExtra()->attach(Permiso::query()->where(['modulo' => 'pagos', 'accion' => 'crear'])->value('id'), ['concedido' => true]);
        $this->postJson("/api/v1/importaciones/{$id}/decisiones", ['generar_asiento' => true])->assertForbidden();
        $this->postJson("/api/v1/importaciones/{$id}/decisiones", ['generar_asiento' => false])->assertOk();
        $this->assertSame(false, $this->importacion($id)->decision_generar_asiento);
    }

    public function test_generar_el_asiento_de_creditos_sin_cuenta_de_contrapartida_responde_422(): void
    {
        $this->actingAsAdmin();
        $this->cliente();
        $this->reglasDeDesembolso();
        $id = $this->subirArchivo('creditos', self::CREDITOS.$this->linea(['CR-1', '1001', '01/10/2023', '2000000', '2,5', '4', 'mensual', '', 'No', '', '', '', '']));
        $this->ejecutarValidacion($id);

        $this->postJson("/api/v1/importaciones/{$id}/decisiones", ['generar_asiento' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('generar_asiento');
    }

    public function test_confirmar_sin_decidir_el_asiento_pendiente_responde_422(): void
    {
        $this->actingAsAdmin();
        $this->cliente();
        $this->reglasDeRecaudo();
        $this->credito();
        $valor = $this->valorDeCuota('CR-1', 1);
        $id = $this->subirArchivo('pagos', self::PAGOS."CR-1;14/11/2023;{$valor};Cuota completa;;;;\n");
        $this->ejecutarValidacion($id);

        $this->postJson("/api/v1/importaciones/{$id}/confirmar")
            ->assertUnprocessable()->assertJsonValidationErrors('generar_asiento');
    }

    public function test_confirmar_con_una_fotografia_vieja_de_la_importacion_no_escribe_dos_veces(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n");
        $this->ejecutarValidacion($id);
        $vieja = $this->importacion($id);
        $this->postJson("/api/v1/importaciones/{$id}/confirmar")->assertStatus(202);

        $usuario = Usuario::query()->findOrFail($vieja->usuario_id);
        $this->expectException(ValidationException::class);
        app(ImportacionService::class)->confirmar($vieja, $usuario);
    }

    public function test_la_guia_de_creditos_describe_montos_tasas_y_respuestas_si_no(): void
    {
        $this->actingAsAdmin();

        $contenido = $this->get('/api/v1/importaciones/guias/creditos')->assertOk()->streamedContent();

        $this->assertStringContainsString('Monto: 2.000.000', $contenido);
        $this->assertStringContainsString('Número con decimales', $contenido);
        $this->assertStringContainsString('Número entero', $contenido);
        $this->assertStringContainsString('Sí o No', $contenido);
        $this->assertStringContainsString('Fecha: dd/mm/aaaa', $contenido);
    }

    public function test_un_tipo_de_guia_desconocido_responde_404(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/importaciones/guias/no_existe')->assertNotFound();
    }

    public function test_clientes_con_datos_invalidos_se_rechazan_cada_uno_con_su_motivo(): void
    {
        $this->actingAsAdmin();

        $id = $this->importarCompleto('clientes', self::CLIENTES
            .";Sin cédula Apellido;;;;;;;;\n"
            ."1001;Ana Pérez;;correo-malo;;;;;;\n"
            ."1002;Luis Ramírez;;;;;;;2023.13.01;\n");

        $this->assertSame(0, $this->importacion($id)->filas_aceptadas);
        $motivos = $this->motivos($id);
        $this->assertStringContainsString('La cédula es obligatoria', $motivos);
        $this->assertStringContainsString('correo', strtolower($motivos));
        $this->assertStringContainsString('Fecha no reconocida', $motivos);
    }

    public function test_creditos_con_campos_vacios_o_invalidos_se_rechazan_uno_por_uno(): void
    {
        $this->actingAsAdmin();
        $this->cliente();

        $id = $this->importarCompleto('creditos', self::CREDITOS
            .$this->linea(['CR-A', '1001', '', '2000000', '2,5', '4', 'mensual', '', 'No', '', '', '', ''])
            .$this->linea(['CR-B', '1001', '01/10/2023', '', '2,5', '4', 'mensual', '', 'No', '', '', '', ''])
            .$this->linea(['CR-C', '1001', '01/10/2023', '2000000', '', '4', 'mensual', '', 'No', '', '', '', ''])
            .$this->linea(['CR-D', '1001', '01/10/2023', '2000000', '2,5', '', 'mensual', '', 'No', '', '', '', ''])
            .$this->linea(['CR-E', '1001', '01/10/2023', '0', '2,5', '4', 'mensual', '', 'No', '', '', '', ''])
            .$this->linea(['CR-F', '1001', '01/10/2023', '2000000', '0', '4', 'mensual', '', 'No', '', '', '', ''])
            .$this->linea(['CR-G', '1001', '01/10/2023', '2000000', '2,5', '0', 'mensual', '', 'No', '', '', '', ''])
            .$this->linea(['CR-H', '1001', '01/10/2023', '2000000', '2,5', '4', 'mensual', '', 'No', 'CR-A', '', '', ''])
            .$this->linea(['CR-I', '1001', '01/10/2023', '2000000', '2,5', '4', 'mensual', '', 'No', '', '', '', ''])
            .$this->linea(['CR-I', '1001', '01/10/2023', '2000000', '2,5', '4', 'mensual', '', 'No', '', '', '', '']));

        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);
        $motivos = $this->motivos($id);
        $this->assertStringContainsString('El valor desembolsado es obligatorio', $motivos);
        $this->assertStringContainsString('La fecha de desembolso es obligatoria', $motivos);
        $this->assertStringContainsString('La tasa de interés es obligatoria', $motivos);
        $this->assertStringContainsString('El plazo es obligatorio', $motivos);
        $this->assertStringContainsString('mayor que cero', $motivos);
        $this->assertStringContainsString('código anterior', $motivos);
        $this->assertStringContainsString('repetido en el archivo', $motivos);
    }

    public function test_el_plan_de_cuotas_valida_cada_campo_y_respeta_las_cuotas_ya_iguales(): void
    {
        $this->actingAsAdmin();
        $this->cliente();
        $this->credito();
        $cuota = Cuota::query()->whereHas('credito', fn ($q) => $q->where('codigo', 'CR-1'))->where('numero_cuota', 1)->firstOrFail();

        $id = $this->importarCompleto('plan_cuotas', self::PLAN
            .$this->linea(['', '1', '14/11/2023', '450000', '150000', '600000', 'Pendiente'])
            .$this->linea(['CR-1', '', '14/11/2023', '450000', '150000', '600000', 'Pendiente'])
            .$this->linea(['CR-1', '1', '', '450000', '150000', '600000', 'Pendiente'])
            .$this->linea(['CR-1', '1', '14/11/2023', '450000', '150000', '0', 'Pendiente'])
            .$this->linea(['CR-1', '1', '14/11/2023', '450000', '150000', '', 'Pendiente'])
            .$this->linea(['CR-1', '1', '14/11/2023', '', '', '600000', 'Pendiente'])
            .$this->linea(['CR-1', '2', '14/11/2023', '450000', '150000', '600000', 'Pendiente'])
            .$this->linea(['CR-1', '2', '14/11/2023', '450000', '150000', '600000', 'Pendiente'])
            .$this->linea([$cuota->fecha_vencimiento->format('Y-m-d'), '1', '', '', '', '', '']));

        $this->assertSame(0, $this->importacion($id)->filas_pendientes);
        $motivos = $this->motivos($id);
        $this->assertStringContainsString('código de crédito es obligatorio', $motivos);
        $this->assertStringContainsString('número de cuota es obligatorio', $motivos);
        $this->assertStringContainsString('La fecha de vencimiento es obligatoria', $motivos);
        $this->assertStringContainsString('valor total de la cuota debe ser mayor que cero', $motivos);
        $this->assertStringContainsString('El valor total de la cuota es obligatorio', $motivos);
        $this->assertStringContainsString('indica el valor capital', $motivos);
        $this->assertStringContainsString('repetida en el archivo', $motivos);
    }

    public function test_una_fila_del_plan_igual_a_la_cuota_actual_se_acepta_sin_cambiar_nada(): void
    {
        $this->actingAsAdmin();
        $this->cliente();
        $this->credito();
        $cuota = Cuota::query()->whereHas('credito', fn ($q) => $q->where('codigo', 'CR-1'))->where('numero_cuota', 1)->firstOrFail();
        $antes = $cuota->valor->centavos();

        $id = $this->importarCompleto('plan_cuotas', self::PLAN.$this->linea([
            'CR-1', '1', $cuota->fecha_vencimiento->format('Y-m-d'), $cuota->valor_capital->aDecimalString(), $cuota->valor_interes->aDecimalString(), $cuota->valor->aDecimalString(), 'Pendiente',
        ]));

        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);
        $this->assertSame($antes, $cuota->fresh()->valor->centavos());
        $this->assertSame(0, $this->importacion($id)->filas_rechazadas);
    }

    public function test_los_pagos_validan_cada_campo_y_cada_regla_del_plan(): void
    {
        $this->actingAsAdmin();
        $this->cliente();
        $this->credito();
        $valor = $this->valorDeCuota('CR-1', 1);
        $segunda = $this->valorDeCuota('CR-1', 2);

        $id = $this->importarCompleto('pagos', self::PAGOS
            .$this->linea(['', '14/11/2023', $valor, 'Cuota completa', '', '', '', ''])
            .$this->linea(['CR-1', '', $valor, 'Cuota completa', '', '', '', ''])
            .$this->linea(['CR-1', '14/11/2023', '', 'Cuota completa', '', '', '', ''])
            .$this->linea(['CR-1', '14/11/2023', '0', 'Cuota completa', '', '', '', ''])
            .$this->linea(['CR-1', '14/11/2023', '123', 'Cuota completa', '', '', '', ''])
            .$this->linea(['CR-1', '14/11/2023', $valor, 'Cuota completa', '3', '', '', ''])
            .$this->linea(['CR-1', '14/11/2023', $segunda, 'Cuota completa', '2', '', '', ''])
            .$this->linea(['CR-1', '14/11/2023', $valor, 'Abono parcial', '', '', '', ''])
            .$this->linea(['CR-1', '14/11/2023', $valor, 'Pagado a medias', '', '', '', '']));

        $this->assertSame(0, $this->importacion($id)->filas_aceptadas);
        $motivos = $this->motivos($id);
        $this->assertStringContainsString('código de crédito es obligatorio', $motivos);
        $this->assertStringContainsString('fecha de pago es obligatoria', $motivos);
        $this->assertStringContainsString('valor pagado es obligatorio', $motivos);
        $this->assertStringContainsString('mayor que cero', $motivos);
        $this->assertStringContainsString('no cuadra con el plan', $motivos);
        $this->assertStringContainsString('no coincide con el saldo de la cuota 1', $motivos);
        $this->assertStringContainsString('usa Cuota completa', $motivos);
        $this->assertStringContainsString('Tipo no reconocido', $motivos);
    }

    public function test_los_saldos_iniciales_validan_fecha_cuenta_valores_y_repetidos(): void
    {
        $this->actingAsAdmin();
        $this->importarCompleto('plan_cuentas', "codigo,nombre,naturaleza,tipo\n110505,Caja,debito,activo\n");

        $id = $this->importarCompleto('saldos_iniciales', "Fecha de corte,Cuenta PUC,Saldo débito,Saldo crédito\n"
            .",110505,100,0\n"
            ."2024-12-31,,100,0\n"
            ."2024-12-31,110505,0,0\n"
            ."2024-12-31,110505,100,0\n"
            ."2024-12-31,110505,100,0\n");

        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);
        $motivos = $this->motivos($id);
        $this->assertStringContainsString('La fecha de corte es obligatoria', $motivos);
        $this->assertStringContainsString('La cuenta PUC es obligatoria', $motivos);
        $this->assertStringContainsString('Indica el saldo débito o el saldo crédito', $motivos);
        $this->assertStringContainsString('repetido en el archivo', $motivos);
    }

    public function test_los_terceros_rechazan_el_tipo_desconocido_los_repetidos_y_los_ya_registrados(): void
    {
        $this->actingAsAdmin();
        $this->cliente('9001');

        $encabezado = 'tipo,tipo_persona,tipo_documento,numero_documento,nombres,apellidos,razon_social,digito_verificacion,representante_legal';
        $id = $this->importarCompleto('terceros', "{$encabezado}\n"
            ."empleado,natural,cc,1000000001,Ana,Torres,,,\n"
            ."proveedor,natural,cc,1000000002,Luis,Gómez,,,\n"
            ."proveedor,natural,cc,1000000002,Luis,Gómez,,,\n"
            ."cliente,natural,cc,9001,Ana,Pérez,,,\n"
            ."otro,natural,cc,9001,Ana,Pérez,,,\n");

        $this->assertSame(2, $this->importacion($id)->filas_aceptadas);
        $motivos = $this->motivos($id);
        $this->assertStringContainsString('Tipo de tercero inválido', $motivos);
        $this->assertStringContainsString('repetido en el archivo', $motivos);
        $this->assertStringContainsString('ya está registrado como cliente', $motivos);
    }

    public function test_el_plan_de_cuentas_rechaza_codigo_repetido_naturaleza_y_tipo_invalidos(): void
    {
        $this->actingAsAdmin();
        $this->importarCompleto('plan_cuentas', "codigo,nombre,naturaleza,tipo\n110505,Caja,debito,activo\n");

        $id = $this->importarCompleto('plan_cuentas', "codigo,nombre,naturaleza,tipo\n110505,Caja repetida,debito,activo\n110510,Bancos,deudora,activo\n110515,Otra,debito,inventario\n110520,Doble,debito,activo\n110520,Doble,debito,activo\n");

        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);
        $motivos = $this->motivos($id);
        $this->assertStringContainsString('ya está en uso', $motivos);
        $this->assertStringContainsString('repetido en el archivo', $motivos);
    }

    public function test_un_saldo_de_una_cuenta_sin_movimiento_permitido_se_registra_igual(): void
    {
        $this->actingAsAdmin();
        $this->importarCompleto('plan_cuentas', "codigo,nombre,naturaleza,tipo\n110505,Caja,debito,activo\n");

        $id = $this->importarCompleto('saldos_iniciales', "Fecha de corte,Cuenta PUC,Saldo débito,Saldo crédito\n31/12/2024,110505,0,250\n");

        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);
        $this->assertDatabaseCount('saldos_apertura', 1);
    }

    public function test_un_pago_que_excede_el_saldo_no_se_descarta_en_silencio(): void
    {
        $this->actingAsAdmin();
        $this->cliente();
        $this->credito();
        $credito = Credito::query()->where('codigo', 'CR-1')->firstOrFail();
        $total = Cuota::query()->where('credito_id', $credito->id)->get()->reduce(fn (Dinero $acumulado, Cuota $cuota) => $acumulado->sumar($cuota->valor), Dinero::cero());

        $this->expectException(ValidationException::class);
        app(PagoService::class)->registrarPagoHistorico(
            $credito,
            $total->sumar(Dinero::desdePesos(1)),
            '2023-11-14',
            [],
            null,
            false,
        );
    }

    public function test_sin_una_regla_de_desembolso_el_asiento_de_apertura_se_rechaza(): void
    {
        $this->actingAsAdmin();
        $comprobantes = app(ComprobanteService::class);
        $contrapartida = $this->crearCuenta(['codigo' => '390505', 'nombre' => 'Apertura']);

        $this->assertFalse($comprobantes->eventosContablesActivos(EventoContable::DesembolsoCredito));
        $this->assertNull($comprobantes->contabilizarAperturaCartera(Dinero::cero(), $contrapartida->id, now()->toDateString(), 1));

        $this->expectException(ValidationException::class);

        $comprobantes->contabilizarAperturaCartera(Dinero::desdePesos(100), $contrapartida->id, now()->toDateString(), 1);
    }

    public function test_con_regla_de_desembolso_y_valor_positivo_el_asiento_de_apertura_se_crea(): void
    {
        $this->actingAsAdmin();
        PeriodoContable::create(['anio' => now()->year, 'mes' => now()->month, 'estado' => EstadoPeriodo::Abierto]);
        $contrapartida = $this->reglasDeDesembolso();
        $comprobantes = app(ComprobanteService::class);

        $this->assertTrue($comprobantes->eventosContablesActivos(EventoContable::DesembolsoCredito));
        $this->assertNull($comprobantes->contabilizarAperturaCartera(Dinero::cero(), $contrapartida->id, now()->toDateString(), 1));
        $comprobante = $comprobantes->contabilizarAperturaCartera(Dinero::desdePesos(100), $contrapartida->id, now()->toDateString(), 1);
        $this->assertNotNull($comprobante);
        $this->assertSame('importaciones', $comprobante->modulo_origen);
    }

    public function test_los_repositorios_no_consultan_nada_con_listas_vacias(): void
    {
        $this->actingAsAdmin();
        $repo = app(DatosImportacionRepository::class);

        $this->assertSame([], $repo->clientesPorCedulas(1, []));
        $this->assertSame([], $repo->creditosPorCodigos(1, []));
        $this->assertSame([], $repo->cuentasPorCodigos(1, []));
    }

    public function test_el_lector_falla_con_un_mensaje_claro_si_el_archivo_no_existe(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo abrir el archivo de importación.');

        iterator_to_array((new LectorCsv('/no/existe/archivo.csv', TipoImportacion::Clientes))->filas());
    }

    public function test_las_relaciones_de_la_importacion_devuelven_sus_datos(): void
    {
        $admin = $this->actingAsAdmin();
        $contrapartida = $this->crearCuenta(['codigo' => '390505', 'nombre' => 'Apertura']);
        $id = $this->subirArchivo('clientes', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n");
        $this->ejecutarValidacion($id);
        $importacion = $this->importacion($id);
        $importacion->forceFill(['cuenta_contrapartida_id' => $contrapartida->id])->save();

        $this->assertSame($admin->id, $importacion->usuario->id);
        $this->assertSame($admin->empresa_id, $importacion->empresa->id);
        $this->assertSame($contrapartida->id, $importacion->cuentaContrapartida->id);
        $this->assertCount(0, $importacion->errores);
    }

    public function test_el_error_pertenece_a_su_importacion(): void
    {
        $this->actingAsAdmin();
        $id = $this->importarCompleto('clientes', self::CLIENTES.";Sin cédula Apellido\n");

        $error = ImportacionError::query()->where('importacion_id', $id)->firstOrFail();

        $this->assertSame($id, $error->importacion->id);
        $this->assertSame(Empresa::query()->findOrFail($this->importacion($id)->empresa_id)->id, $error->empresa_id);
    }

    public function test_los_jobs_se_encolan_en_la_cola_y_conexion_de_importaciones(): void
    {
        $this->actingAsAdmin();

        $validar = new ValidarImportacionJob(1, 1);
        $escribir = new EscribirImportacionJob(1, 1);

        $this->assertSame('redis', $validar->connection);
        $this->assertSame('importaciones', $validar->queue);
        $this->assertSame('redis', $escribir->connection);
        $this->assertSame('importaciones', $escribir->queue);
    }

    public function test_la_conversion_de_valores_rechaza_lo_que_no_puede_leer_sin_adivinar(): void
    {
        $this->assertNull(ConvertidorValor::fecha('', 'f'));
        $this->assertNull(ConvertidorValor::monto('', 'v'));
        $this->assertNull(ConvertidorValor::decimal('', 'v'));
        $this->assertNull(ConvertidorValor::entero('', 'v'));
        $this->assertNull(ConvertidorValor::siNo('', 'v'));

        $this->assertRechazo(fn () => ConvertidorValor::fecha('0', 'fecha'), 'fuera de rango');
        $this->assertRechazo(fn () => ConvertidorValor::fecha('1234567', 'fecha'), 'no reconocida');
        $this->assertRechazo(fn () => ConvertidorValor::monto('12.5.5', 'valor'), 'no reconocido');
        $this->assertRechazo(fn () => ConvertidorValor::decimal('abc', 'tasa'), 'no reconocido');
        $this->assertRechazo(fn () => ConvertidorValor::entero('x1', 'plazo'), 'no reconocido');
    }

    public function test_pedir_asiento_en_una_hoja_que_no_contabiliza_responde_422(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/importaciones', [
            'tipo' => 'clientes',
            'generar_asiento' => '1',
            'archivo' => UploadedFile::fake()->createWithContent('c.csv', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n"),
        ])->assertUnprocessable()->assertJsonValidationErrors('generar_asiento');
    }

    public function test_si_el_archivo_desaparece_entre_la_validacion_y_la_escritura_la_importacion_falla(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n");
        $this->ejecutarValidacion($id);
        $this->postJson("/api/v1/importaciones/{$id}/confirmar")->assertStatus(202);
        Storage::disk('importaciones')->delete($this->importacion($id)->getRawOriginal('archivo_ruta'));

        $this->ejecutarEscritura($id);

        $this->assertSame(EstadoImportacion::Fallida, $this->importacion($id)->estado);
        $this->assertDatabaseCount('clientes', 0);
        $this->assertDatabaseHas('notificaciones', ['tipo' => 'importacion_fallida']);
    }

    public function test_el_job_de_escritura_registra_su_fallo_como_importacion_fallida(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::CLIENTES."1001;Ana Pérez;;;;;;;;\n");
        $this->ejecutarValidacion($id);
        $this->postJson("/api/v1/importaciones/{$id}/confirmar")->assertStatus(202);

        (new EscribirImportacionJob($id, $this->importacion($id)->empresa_id))->failed(new RuntimeException('timeout'));

        $this->assertSame(EstadoImportacion::Fallida, $this->importacion($id)->estado);
    }

    private function assertRechazo(callable $operacion, string $fragmento): void
    {
        try {
            $operacion();
            $this->fail('Se esperaba un rechazo.');
        } catch (FilaRechazada $excepcion) {
            $this->assertStringContainsString($fragmento, $excepcion->getMessage());
        }
    }

    private function asesorDeLaEmpresa(Usuario $admin): Usuario
    {
        $asesor = $this->actingAsAsesor(Empresa::query()->findOrFail($admin->empresa_id));
        foreach (['listar', 'ejecutar'] as $accion) {
            $asesor->permisosExtra()->attach(Permiso::query()->where(['modulo' => 'importaciones', 'accion' => $accion])->value('id'), ['concedido' => true]);
        }

        return $asesor;
    }
}
