<?php

namespace Tests\Feature\Gastos;

use App\Causacion\Services\MotorReglasFiscalesService;
use App\Contabilidad\Enums\EstadoComprobante;
use App\Core\Dinero\Dinero;
use App\Gastos\Models\Gasto;
use App\Terceros\Enums\TipoPersona;
use App\Terceros\Models\Tercero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

class GastoControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function archivoSoporte(): UploadedFile
    {
        return UploadedFile::fake()->create('soporte.pdf', 200, 'application/pdf');
    }

    public function test_causa_un_gasto_de_honorarios_con_las_tres_retenciones_y_trazabilidad(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt(2026, 47065);
        $tipoGasto = $this->crearTipoGasto(['aplica_iva' => true]);
        $municipio = $this->crearMunicipio();
        $actividad = $this->crearActividadEconomica();
        $this->crearTarifaReteIca($municipio, $actividad, 6.9);
        $tercero = $this->crearTerceroFiscal([
            'tipo_persona' => TipoPersona::Natural,
            'responsable_iva' => true,
            'actividad_economica_id' => $actividad->id,
        ]);
        $this->crearParametrizacion($tipoGasto, [
            'tarifa_iva' => 19,
            'cuenta_iva_id' => $this->crearCuenta()->id,
            'aplica_retefuente' => true,
            'tarifa_retefuente_persona_natural' => 10,
            'tarifa_retefuente_persona_juridica' => 11,
            'cuenta_retefuente_id' => $this->crearCuenta()->id,
            'aplica_reteica' => true,
            'cuenta_reteica_id' => $this->crearCuenta()->id,
            'aplica_reteiva' => true,
            'tarifa_reteiva' => 15,
            'cuenta_reteiva_id' => $this->crearCuenta()->id,
        ]);

        $respuesta = $this->postJson('/api/v1/gastos', [
            'tipo_gasto_id' => $tipoGasto->id, 'centro_costo_id' => $this->crearCentroCosto()->id,
            'tercero_id' => $tercero->id,
            'municipio_id' => $municipio->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FAC-100',
            'concepto' => 'Honorarios marzo',
            'valor_bruto' => 1000000,
            'soporte' => $this->archivoSoporte(),
        ]);

        $respuesta->assertStatus(201)
            ->assertJsonPath('gasto.valor_iva', '190000.00')
            ->assertJsonPath('gasto.valor_retefuente', '100000.00')
            ->assertJsonPath('gasto.valor_reteica', '6900.00')
            ->assertJsonPath('gasto.valor_reteiva', '28500.00')
            ->assertJsonPath('gasto.valor_neto', '1054600.00');

        $gastoId = $respuesta->json('gasto.id');
        $this->assertDatabaseCount('gasto_reglas_fiscales_aplicadas', 3);
        $this->assertDatabaseHas('gasto_reglas_fiscales_aplicadas', [
            'gasto_id' => $gastoId,
            'tipo_retencion' => 'retefuente',
            'incluida' => true,
            'regla' => 'tarifa_por_tipo_persona',
        ]);
    }

    public function test_excluye_retefuente_si_el_tercero_es_autorretenedor(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto();
        $tercero = $this->crearTerceroFiscal(['autorretenedor' => true]);
        $this->crearParametrizacion($tipoGasto, [
            'aplica_retefuente' => true,
            'tarifa_retefuente_persona_natural' => 10,
            'tarifa_retefuente_persona_juridica' => 11,
            'cuenta_retefuente_id' => $this->crearCuenta()->id,
        ]);

        $respuesta = $this->postJson('/api/v1/gastos', [
            'tipo_gasto_id' => $tipoGasto->id, 'centro_costo_id' => $this->crearCentroCosto()->id,
            'tercero_id' => $tercero->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FAC-101',
            'concepto' => 'Arriendo a autorretenedor',
            'valor_bruto' => 1000000,
            'soporte' => $this->archivoSoporte(),
        ]);

        $respuesta->assertStatus(201)->assertJsonPath('gasto.valor_retefuente', '0.00')->assertJsonPath('gasto.valor_neto', '1000000.00');
        $this->assertDatabaseHas('gasto_reglas_fiscales_aplicadas', [
            'gasto_id' => $respuesta->json('gasto.id'),
            'tipo_retencion' => 'retefuente',
            'incluida' => false,
            'regla' => 'autorretenedor',
        ]);
    }

    public function test_excluye_reteiva_si_el_tercero_no_es_responsable_de_iva(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto();
        $tercero = $this->crearTerceroFiscal(['responsable_iva' => false]);
        $this->crearParametrizacion($tipoGasto, [
            'aplica_reteiva' => true,
            'tarifa_reteiva' => 15,
            'cuenta_reteiva_id' => $this->crearCuenta()->id,
        ]);

        $respuesta = $this->postJson('/api/v1/gastos', [
            'tipo_gasto_id' => $tipoGasto->id, 'centro_costo_id' => $this->crearCentroCosto()->id,
            'tercero_id' => $tercero->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FAC-102',
            'concepto' => 'Servicio a no responsable de IVA',
            'valor_bruto' => 1000000,
            'soporte' => $this->archivoSoporte(),
        ]);

        $respuesta->assertStatus(201)->assertJsonPath('gasto.valor_reteiva', '0.00');
        $this->assertDatabaseHas('gasto_reglas_fiscales_aplicadas', [
            'gasto_id' => $respuesta->json('gasto.id'),
            'tipo_retencion' => 'reteiva',
            'incluida' => false,
            'regla' => 'no_responsable_iva',
        ]);
    }

    public function test_excluye_retefuente_por_debajo_de_la_base_minima_en_uvt(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt(2026, 47065);
        $tipoGasto = $this->crearTipoGasto();
        $tercero = $this->crearTerceroFiscal();
        $this->crearParametrizacion($tipoGasto, [
            'aplica_retefuente' => true,
            'base_minima_uvt' => 30,
            'tarifa_retefuente_persona_natural' => 10,
            'tarifa_retefuente_persona_juridica' => 11,
            'cuenta_retefuente_id' => $this->crearCuenta()->id,
        ]);

        $respuesta = $this->postJson('/api/v1/gastos', [
            'tipo_gasto_id' => $tipoGasto->id, 'centro_costo_id' => $this->crearCentroCosto()->id,
            'tercero_id' => $tercero->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FAC-103',
            'concepto' => 'Servicio menor a la base mínima',
            'valor_bruto' => 500000,
            'soporte' => $this->archivoSoporte(),
        ]);

        $respuesta->assertStatus(201)->assertJsonPath('gasto.valor_retefuente', '0.00');
        $this->assertDatabaseHas('gasto_reglas_fiscales_aplicadas', [
            'gasto_id' => $respuesta->json('gasto.id'),
            'tipo_retencion' => 'retefuente',
            'incluida' => false,
            'regla' => 'bajo_base_minima',
        ]);
    }

    public function test_aplica_tarifa_diferencial_de_retefuente_segun_tipo_de_persona(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto();
        $parametrizacion = $this->crearParametrizacion($tipoGasto, [
            'aplica_retefuente' => true,
            'tarifa_retefuente_persona_natural' => 10,
            'tarifa_retefuente_persona_juridica' => 11,
            'cuenta_retefuente_id' => $this->crearCuenta()->id,
        ]);
        $terceroNatural = $this->crearTerceroFiscal(['tipo_persona' => TipoPersona::Natural]);
        $terceroJuridico = $this->crearTerceroFiscal(['tipo_persona' => TipoPersona::Juridica]);

        $respuestaNatural = $this->postJson('/api/v1/gastos', [
            'tipo_gasto_id' => $tipoGasto->id, 'centro_costo_id' => $this->crearCentroCosto()->id, 'tercero_id' => $terceroNatural->id, 'fecha' => '2026-03-01',
            'numero_documento' => 'FAC-104', 'concepto' => 'Honorarios persona natural', 'valor_bruto' => 1000000,
            'soporte' => $this->archivoSoporte(),
        ]);
        $respuestaJuridica = $this->postJson('/api/v1/gastos', [
            'tipo_gasto_id' => $tipoGasto->id, 'centro_costo_id' => $this->crearCentroCosto()->id, 'tercero_id' => $terceroJuridico->id, 'fecha' => '2026-03-01',
            'numero_documento' => 'FAC-105', 'concepto' => 'Honorarios persona jurídica', 'valor_bruto' => 1000000,
            'soporte' => $this->archivoSoporte(),
        ]);

        $respuestaNatural->assertJsonPath('gasto.valor_retefuente', '100000.00');
        $respuestaJuridica->assertJsonPath('gasto.valor_retefuente', '110000.00');
    }

    public function test_el_motor_no_asume_persona_natural_si_el_tercero_no_tiene_tipo_de_persona(): void
    {
        $this->actingAsAdmin();
        $uvt = $this->crearValorUvt();
        $parametrizacion = $this->crearParametrizacion($this->crearTipoGasto(), [
            'aplica_retefuente' => true,
            'tarifa_retefuente_persona_natural' => 10,
            'tarifa_retefuente_persona_juridica' => 11,
            'cuenta_retefuente_id' => $this->crearCuenta()->id,
        ]);
        $terceroSinTipo = new Tercero(['autorretenedor' => false]);

        try {
            app(MotorReglasFiscalesService::class)->evaluar(Dinero::desdePesos(1000000), $parametrizacion, $terceroSinTipo, null, $uvt);
            $this->fail('El motor debió rechazar un tercero sin tipo de persona.');
        } catch (ValidationException $excepcion) {
            $this->assertSame([__('validation.custom.tercero.tipo_persona.requerido_para_retefuente')], $excepcion->errors()['tercero_id']);
        }
    }

    public function test_el_motor_no_exige_tipo_de_persona_si_el_tipo_de_gasto_no_aplica_retefuente(): void
    {
        $this->actingAsAdmin();
        $uvt = $this->crearValorUvt();
        $parametrizacion = $this->crearParametrizacion($this->crearTipoGasto());

        $resultado = app(MotorReglasFiscalesService::class)->evaluar(Dinero::desdePesos(1000000), $parametrizacion, new Tercero, null, $uvt);

        $this->assertTrue($resultado['valor_neto']->igualA(Dinero::desdePesos(1000000)));
    }

    public function test_genera_un_asiento_balanceado(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto();
        $tercero = $this->crearTerceroFiscal();
        $this->crearParametrizacion($tipoGasto, [
            'aplica_retefuente' => true,
            'tarifa_retefuente_persona_natural' => 10,
            'tarifa_retefuente_persona_juridica' => 11,
            'cuenta_retefuente_id' => $this->crearCuenta()->id,
        ]);

        $respuesta = $this->postJson('/api/v1/gastos', [
            'tipo_gasto_id' => $tipoGasto->id, 'centro_costo_id' => $this->crearCentroCosto()->id, 'tercero_id' => $tercero->id, 'fecha' => '2026-03-01',
            'numero_documento' => 'FAC-106', 'concepto' => 'Servicio', 'valor_bruto' => 1000000,
            'soporte' => $this->archivoSoporte(),
        ]);

        $comprobanteId = $respuesta->json('gasto.comprobante_id');
        $totales = DB::table('asientos_contables')->where('comprobante_id', $comprobanteId)
            ->selectRaw('SUM(debito) as total_debito, SUM(credito) as total_credito')->first();

        $this->assertEquals((float) $totales->total_debito, (float) $totales->total_credito);
        $this->assertGreaterThan(0, (float) $totales->total_debito);
    }

    public function test_crea_un_documento_tercero_pendiente_por_el_valor_neto(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto();
        $tercero = $this->crearTerceroFiscal();
        $this->crearParametrizacion($tipoGasto);

        $respuesta = $this->postJson('/api/v1/gastos', [
            'tipo_gasto_id' => $tipoGasto->id, 'centro_costo_id' => $this->crearCentroCosto()->id, 'tercero_id' => $tercero->id, 'fecha' => '2026-03-01',
            'numero_documento' => 'FAC-107', 'concepto' => 'Servicio', 'valor_bruto' => 1000000,
            'soporte' => $this->archivoSoporte(),
        ]);

        $this->assertDatabaseHas('documentos_tercero', [
            'tercero_id' => $tercero->id,
            'naturaleza' => 'por_pagar',
            'modulo_origen' => 'gastos',
            'origen_id' => $respuesta->json('gasto.id'),
            'valor_original' => 100000000,
            'saldo' => 100000000,
            'estado' => 'pendiente',
        ]);
    }

    public function test_el_soporte_queda_adjunto_al_gasto(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto();
        $tercero = $this->crearTerceroFiscal();
        $this->crearParametrizacion($tipoGasto);

        $respuesta = $this->postJson('/api/v1/gastos', [
            'tipo_gasto_id' => $tipoGasto->id, 'centro_costo_id' => $this->crearCentroCosto()->id, 'tercero_id' => $tercero->id, 'fecha' => '2026-03-01',
            'numero_documento' => 'FAC-110', 'concepto' => 'Servicio', 'valor_bruto' => 1000000,
            'soporte' => UploadedFile::fake()->create('factura-proveedor.pdf', 300, 'application/pdf'),
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('archivos_adjuntos', [
            'adjuntable_type' => (new Gasto)->getMorphClass(),
            'adjuntable_id' => $respuesta->json('gasto.id'),
            'nombre_original' => 'factura-proveedor.pdf',
        ]);
    }

    public function test_bloquea_causacion_si_aplica_reteica_pero_no_hay_tarifa_vigente(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto();
        $municipio = $this->crearMunicipio();
        $actividad = $this->crearActividadEconomica();
        $tercero = $this->crearTerceroFiscal(['actividad_economica_id' => $actividad->id]);
        $this->crearParametrizacion($tipoGasto, [
            'aplica_reteica' => true,
            'cuenta_reteica_id' => $this->crearCuenta()->id,
        ]);

        $respuesta = $this->postJson('/api/v1/gastos', [
            'tipo_gasto_id' => $tipoGasto->id, 'centro_costo_id' => $this->crearCentroCosto()->id, 'tercero_id' => $tercero->id, 'municipio_id' => $municipio->id,
            'fecha' => '2026-03-01', 'numero_documento' => 'FAC-108', 'concepto' => 'Servicio sin tarifa ICA', 'valor_bruto' => 1000000,
            'soporte' => $this->archivoSoporte(),
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('municipio_id');
        $this->assertDatabaseMissing('gastos', ['numero_documento' => 'FAC-108']);
    }

    public function test_anula_un_gasto_sin_cruces_reversa_el_comprobante_y_elimina_el_documento(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto();
        $tercero = $this->crearTerceroFiscal();
        $this->crearParametrizacion($tipoGasto);

        $creado = $this->postJson('/api/v1/gastos', [
            'tipo_gasto_id' => $tipoGasto->id, 'centro_costo_id' => $this->crearCentroCosto()->id, 'tercero_id' => $tercero->id, 'fecha' => '2026-03-01',
            'numero_documento' => 'FAC-109', 'concepto' => 'Servicio', 'valor_bruto' => 1000000,
            'soporte' => $this->archivoSoporte(),
        ]);
        $gastoId = $creado->json('gasto.id');
        $comprobanteId = $creado->json('gasto.comprobante_id');

        $respuesta = $this->deleteJson("/api/v1/gastos/{$gastoId}");

        $respuesta->assertStatus(204);
        $this->assertSoftDeleted('gastos', ['id' => $gastoId]);
        $this->assertDatabaseHas('comprobantes', ['id' => $comprobanteId, 'estado' => EstadoComprobante::Anulado->value]);
        $this->assertDatabaseMissing('documentos_tercero', ['modulo_origen' => 'gastos', 'origen_id' => $gastoId, 'borrado_en' => null]);
    }

    public function test_gasto_inexistente_devuelve_404(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/gastos/999999')->assertStatus(404);
    }

    public function test_sin_autenticar_no_puede_causar_gastos(): void
    {
        $respuesta = $this->postJson('/api/v1/gastos', []);

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_causar_gastos(): void
    {
        $this->actingAsAsesor();

        $respuesta = $this->postJson('/api/v1/gastos', []);

        $respuesta->assertStatus(403);
    }

    public function test_valida_campos_obligatorios_al_causar(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/gastos', []);

        $respuesta->assertStatus(422)->assertJsonValidationErrors(['tipo_gasto_id', 'fecha', 'numero_documento', 'concepto', 'valor_bruto', 'soporte']);
    }
}
