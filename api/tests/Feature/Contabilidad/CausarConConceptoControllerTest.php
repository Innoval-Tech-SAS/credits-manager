<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Models\PlanCuenta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

/** Causar un concepto desde Comprobante manual (sin crear ninguna fila `Gasto`): reusa el motor de Causacion. */
class CausarConConceptoControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/contabilidad/comprobantes/causar-concepto';

    private function cuentaCxp(array $overrides = []): PlanCuenta
    {
        return $this->crearCuenta(array_merge([
            'tipo' => TipoCuenta::Pasivo,
            'naturaleza' => NaturalezaCuenta::Credito,
            'exige_tercero' => true,
            'exige_documento_cruce' => true,
        ], $overrides));
    }

    /** Cuenta de proveedores con código del prefijo PUC de CxP (2205xx) pero SIN las flags marcadas: nace así. */
    private function cuentaCxpSinMarcas(array $overrides = []): PlanCuenta
    {
        static $contador = 0;
        $contador++;

        return $this->crearCuenta(array_merge([
            'codigo' => "2205{$contador}",
            'tipo' => TipoCuenta::Pasivo,
            'naturaleza' => NaturalezaCuenta::Credito,
            'exige_tercero' => false,
            'exige_documento_cruce' => false,
        ], $overrides));
    }

    public function test_causa_un_concepto_con_tercero_y_al_contabilizar_crea_el_documento_tercero(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto();
        $tercero = $this->crearTerceroFiscal();
        $this->crearParametrizacion($tipoGasto, ['cuenta_cxp_id' => $this->cuentaCxp()->id]);

        $respuesta = $this->postJson(self::BASE, [
            'tipo_gasto_id' => $tipoGasto->id,
            'tercero_id' => $tercero->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FE-5001',
            'concepto' => 'Honorarios marzo',
            'valor_bruto' => 1000000,
        ]);

        $respuesta->assertStatus(201);
        $comprobanteId = $respuesta->json('comprobante.id');
        $numeroComprobante = $respuesta->json('comprobante.numero');
        $this->assertDatabaseHas('comprobantes', ['id' => $comprobanteId, 'documento_soporte' => 'FE-5001']);
        $this->assertDatabaseHas('asientos_contables', [
            'comprobante_id' => $comprobanteId,
            'tercero_id' => $tercero->id,
            'documento_cruce' => $numeroComprobante,
            'credito' => 100000000,
        ]);
        $this->assertDatabaseMissing('gastos', ['numero_documento' => 'FE-5001']);

        $this->postJson("/api/v1/contabilidad/comprobantes/{$comprobanteId}/revisar")->assertOk();
        $this->postJson("/api/v1/contabilidad/comprobantes/{$comprobanteId}/contabilizar")->assertOk();

        $this->assertDatabaseHas('documentos_tercero', [
            'tercero_id' => $tercero->id,
            'naturaleza' => 'por_pagar',
            'numero_documento' => $numeroComprobante,
            'valor_original' => 100000000,
            'saldo' => 100000000,
            'estado' => 'pendiente',
            'modulo_origen' => 'contabilidad_manual',
            'origen_id' => $comprobanteId,
        ]);
    }

    public function test_causa_un_concepto_con_cuenta_cxp_sin_marcas_pero_con_prefijo_de_cartera(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto();
        $tercero = $this->crearTerceroFiscal();
        $this->crearParametrizacion($tipoGasto, ['cuenta_cxp_id' => $this->cuentaCxpSinMarcas()->id]);

        $respuesta = $this->postJson(self::BASE, [
            'tipo_gasto_id' => $tipoGasto->id,
            'tercero_id' => $tercero->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FE-5011',
            'concepto' => 'Honorarios marzo',
            'valor_bruto' => 1000000,
        ]);

        $respuesta->assertStatus(201);
        $comprobanteId = $respuesta->json('comprobante.id');

        $this->postJson("/api/v1/contabilidad/comprobantes/{$comprobanteId}/revisar")->assertOk();
        $this->postJson("/api/v1/contabilidad/comprobantes/{$comprobanteId}/contabilizar")->assertOk();

        $this->assertDatabaseHas('documentos_tercero', [
            'tercero_id' => $tercero->id,
            'naturaleza' => 'por_pagar',
            'numero_documento' => $respuesta->json('comprobante.numero'),
            'saldo' => 100000000,
            'estado' => 'pendiente',
            'modulo_origen' => 'contabilidad_manual',
            'origen_id' => $comprobanteId,
        ]);
    }

    public function test_cuenta_cxp_de_cartera_exige_tercero_aunque_el_tipo_de_gasto_no_lo_requiera(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto(['requiere_tercero' => false]);
        $this->crearParametrizacion($tipoGasto, ['cuenta_cxp_id' => $this->cuentaCxpSinMarcas()->id]);

        $respuesta = $this->postJson(self::BASE, [
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FE-5012',
            'valor_bruto' => 1000000,
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('lineas');
        $this->assertDatabaseCount('comprobantes', 0);
    }

    public function test_tipo_de_gasto_inexistente_falla(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson(self::BASE, [
            'tipo_gasto_id' => 999999,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FE-5002',
            'valor_bruto' => 1000000,
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('tipo_gasto_id');
    }

    public function test_tipo_de_gasto_inactivo_falla(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto(['activo' => false]);
        $this->crearParametrizacion($tipoGasto);

        $respuesta = $this->postJson(self::BASE, [
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FE-5003',
            'valor_bruto' => 1000000,
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('tipo_gasto_id');
    }

    public function test_exige_tercero_si_el_tipo_de_gasto_lo_requiere(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto(['requiere_tercero' => true]);
        $this->crearParametrizacion($tipoGasto);

        $respuesta = $this->postJson(self::BASE, [
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FE-5004',
            'valor_bruto' => 1000000,
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('tercero_id');
    }

    public function test_falla_si_no_hay_parametrizacion_vigente_para_la_fecha(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto(['requiere_tercero' => false]);

        $respuesta = $this->postJson(self::BASE, [
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FE-5005',
            'valor_bruto' => 1000000,
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('tipo_gasto_id');
        $this->assertDatabaseCount('comprobantes', 0);
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

        $respuesta = $this->postJson(self::BASE, [
            'tipo_gasto_id' => $tipoGasto->id,
            'tercero_id' => $tercero->id,
            'municipio_id' => $municipio->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FE-5006',
            'valor_bruto' => 1000000,
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('municipio_id');
        $this->assertDatabaseCount('comprobantes', 0);
    }

    public function test_no_exige_centro_de_costo_si_la_cuenta_de_gasto_no_lo_pide(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto(['requiere_tercero' => false]);
        $cuentaGasto = $this->crearCuenta(['exige_centro_costo' => false]);
        $this->crearParametrizacion($tipoGasto, ['cuenta_gasto_id' => $cuentaGasto->id]);

        $respuesta = $this->postJson(self::BASE, [
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FE-5007',
            'valor_bruto' => 1000000,
        ]);

        $respuesta->assertStatus(201);
    }

    public function test_exige_centro_de_costo_si_la_cuenta_de_gasto_lo_pide(): void
    {
        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto(['requiere_tercero' => false]);
        $cuentaGasto = $this->crearCuenta(['exige_centro_costo' => true]);
        $this->crearParametrizacion($tipoGasto, ['cuenta_gasto_id' => $cuentaGasto->id]);

        $respuesta = $this->postJson(self::BASE, [
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FE-5008',
            'valor_bruto' => 1000000,
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('lineas');
    }

    public function test_sin_autenticar_no_puede_causar_un_concepto(): void
    {
        $respuesta = $this->postJson(self::BASE, []);

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_causar_un_concepto(): void
    {
        $this->actingAsAsesor();

        $respuesta = $this->postJson(self::BASE, []);

        $respuesta->assertStatus(403);
    }

    public function test_valida_campos_obligatorios(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson(self::BASE, []);

        $respuesta->assertStatus(422)->assertJsonValidationErrors(['tipo_gasto_id', 'fecha', 'numero_documento', 'valor_bruto']);
    }

    public function test_no_encuentra_un_tipo_de_gasto_de_otra_empresa(): void
    {
        $empresaAjena = $this->crearEmpresaDePrueba();
        $this->actingAsAdmin($empresaAjena);
        $this->crearValorUvt();
        $tipoGastoAjeno = $this->crearTipoGasto(['requiere_tercero' => false]);
        $this->crearParametrizacion($tipoGastoAjeno);

        $this->actingAsAdmin();

        $respuesta = $this->postJson(self::BASE, [
            'tipo_gasto_id' => $tipoGastoAjeno->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FE-5009',
            'valor_bruto' => 1000000,
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('tipo_gasto_id');
    }

    public function test_no_encuentra_un_tercero_de_otra_empresa(): void
    {
        $empresaAjena = $this->crearEmpresaDePrueba();
        $this->actingAsAdmin($empresaAjena);
        $terceroAjeno = $this->crearTerceroFiscal();

        $this->actingAsAdmin();
        $this->crearValorUvt();
        $tipoGasto = $this->crearTipoGasto();
        $this->crearParametrizacion($tipoGasto);

        $respuesta = $this->postJson(self::BASE, [
            'tipo_gasto_id' => $tipoGasto->id,
            'tercero_id' => $terceroAjeno->id,
            'fecha' => '2026-03-01',
            'numero_documento' => 'FE-5010',
            'valor_bruto' => 1000000,
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('tercero_id');
    }
}
