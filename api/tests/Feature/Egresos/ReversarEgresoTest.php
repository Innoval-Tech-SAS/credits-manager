<?php

namespace Tests\Feature\Egresos;

use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\DocumentoTercero;
use App\Egresos\Models\Egreso;
use App\Empresas\Models\Empresa;
use App\Gastos\Models\Gasto;
use App\Gastos\Services\GastoService;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

class ReversarEgresoTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    private const MOTIVO = 'Transferencia duplicada por error del banco';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** Gasto causado a un tercero: queda con su documento por pagar (saldo = valor neto) listo para pagarse. */
    private function gastoConDocumentoPendiente(array $overrides = []): Gasto
    {
        $tipoGasto = $this->crearTipoGasto();
        $this->crearParametrizacion($tipoGasto);
        $this->crearValorUvt();
        $centroCosto = $this->crearCentroCosto();
        $tercero = $this->crearTerceroFiscal();

        $gasto = app(GastoService::class)->causar(array_merge([
            'tipo_gasto_id' => $tipoGasto->id,
            'tercero_id' => $tercero->id,
            'centro_costo_id' => $centroCosto->id,
            'fecha' => '2026-01-15',
            'numero_documento' => 'FC-001',
            'concepto' => 'Compra de insumos',
            'valor_bruto' => '1000',
        ], $overrides));

        return $gasto->fresh(['documentoTercero']);
    }

    private function datosValidos(Gasto $gasto): array
    {
        return [
            'tercero_id' => $gasto->tercero_id,
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-20',
            'medio_pago' => 'transferencia',
            'cuenta_caja_id' => $this->crearCuenta()->id,
            'concepto' => 'Pago de la compra',
            'soporte' => UploadedFile::fake()->create('soporte.pdf', 200, 'application/pdf'),
            'aplicaciones' => [
                ['documento_tercero_id' => $gasto->documento_tercero_id, 'valor' => '1000'],
            ],
        ];
    }

    private function crearEgreso(?Gasto $gasto = null): Egreso
    {
        $gasto ??= $this->gastoConDocumentoPendiente();

        $respuesta = $this->postJson('/api/v1/egresos', $this->datosValidos($gasto))->assertStatus(201);

        return Egreso::findOrFail($respuesta->json('egreso.id'));
    }

    private function urlReversa(Egreso $egreso): string
    {
        return "/api/v1/egresos/{$egreso->id}/reversar";
    }

    public function test_sin_autenticar_devuelve_401(): void
    {
        $this->actingAsAdmin();
        $egreso = $this->crearEgreso();
        $this->salirDeSesion();

        $this->postJson($this->urlReversa($egreso), ['motivo' => self::MOTIVO])->assertStatus(401);
    }

    public function test_sin_permiso_de_reversa_recibe_403(): void
    {
        $admin = $this->actingAsAdmin();
        $egreso = $this->crearEgreso();
        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id));

        $this->postJson($this->urlReversa($egreso), ['motivo' => self::MOTIVO])->assertStatus(403);
        $this->assertDatabaseMissing('reversas_egreso', ['egreso_id' => $egreso->id]);
    }

    public function test_egreso_inexistente_o_de_otra_empresa_responde_404(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/v1/egresos/999999/reversar', ['motivo' => self::MOTIVO])->assertStatus(404);

        $egresoAjeno = $this->crearEgreso();
        $this->actingAsAdmin();

        $this->postJson($this->urlReversa($egresoAjeno), ['motivo' => self::MOTIVO])->assertStatus(404);
        $this->assertDatabaseMissing('reversas_egreso', ['egreso_id' => $egresoAjeno->id]);
    }

    public function test_motivo_es_obligatorio_y_tiene_minimo_de_caracteres(): void
    {
        $this->actingAsAdmin();
        $egreso = $this->crearEgreso();

        $this->postJson($this->urlReversa($egreso), [])->assertStatus(422)->assertJsonValidationErrors(['motivo']);
        $this->postJson($this->urlReversa($egreso), ['motivo' => 'abc'])->assertStatus(422)->assertJsonValidationErrors(['motivo']);
        $this->assertDatabaseMissing('reversas_egreso', ['egreso_id' => $egreso->id]);
    }

    public function test_reversa_restaura_el_documento_por_pagar_y_cuadra_el_asiento_de_reverso(): void
    {
        $this->actingAsAdmin();
        $gasto = $this->gastoConDocumentoPendiente();
        $egreso = $this->crearEgreso($gasto);

        $documentoAntes = DocumentoTercero::findOrFail($gasto->documento_tercero_id);
        $this->assertEquals(0, $documentoAntes->saldo->aFloat());
        $this->assertSame('cancelado', $documentoAntes->estado->value);

        $this->postJson($this->urlReversa($egreso), ['motivo' => self::MOTIVO])->assertOk();

        $documentoDespues = DocumentoTercero::findOrFail($gasto->documento_tercero_id);
        $this->assertEquals(1000, $documentoDespues->saldo->aFloat());
        $this->assertSame('pendiente', $documentoDespues->estado->value);

        // El egreso original no se toca: sigue intacto.
        $this->assertDatabaseHas('egresos', ['id' => $egreso->id, 'valor' => 100000]);

        $this->assertDatabaseHas('reversas_egreso', [
            'egreso_id' => $egreso->id,
            'valor' => 100000,
            'motivo' => self::MOTIVO,
        ]);

        $this->assertDatabaseHas('aplicaciones_documento', [
            'documento_tercero_id' => $gasto->documento_tercero_id,
            'modulo_aplicacion' => 'egresos',
            'aplicacion_id' => $egreso->id,
        ]);
        $aplicacionOriginal = DB::table('aplicaciones_documento')
            ->where('documento_tercero_id', $gasto->documento_tercero_id)
            ->where('modulo_aplicacion', 'egresos')
            ->where('aplicacion_id', $egreso->id)
            ->first();
        $this->assertNotNull($aplicacionOriginal->reversado_en);

        $original = Comprobante::where('modulo_origen', 'egresos')->where('origen_id', $egreso->id)->firstOrFail();
        $reverso = Comprobante::where('modulo_origen', 'reverso')->where('origen_id', $original->id)->firstOrFail();

        $this->assertEquals('anulado', $original->estado->value);
        $this->assertEquals($reverso->id, $original->comprobante_reverso_id);
        $this->assertEquals('contabilizado', $reverso->estado->value);
        $this->assertTrue($reverso->total_debito->igualA($reverso->total_credito));
        $this->assertTrue($reverso->total_debito->igualA($original->total_credito));
        $this->assertTrue($reverso->total_credito->igualA($original->total_debito));
    }

    public function test_un_egreso_reversado_no_se_reversa_otra_vez(): void
    {
        $this->actingAsAdmin();
        $egreso = $this->crearEgreso();

        $this->postJson($this->urlReversa($egreso), ['motivo' => self::MOTIVO])->assertOk();

        $this->postJson($this->urlReversa($egreso), ['motivo' => self::MOTIVO])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['egreso']);
        $this->assertDatabaseCount('reversas_egreso', 1);
    }

    public function test_reversar_un_egreso_que_paga_documentos_de_mas_de_una_cuenta_revierte_todas_las_lineas(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTerceroFiscal();
        $gastoUno = $this->gastoConDocumentoPendiente(['tercero_id' => $tercero->id, 'numero_documento' => 'FC-010']);

        // Otro tipo de gasto tiene su propia cuenta CxP: el egreso debe agrupar en dos líneas distintas.
        $otroTipoGasto = $this->crearTipoGasto();
        $this->crearParametrizacion($otroTipoGasto);
        $gastoDos = app(GastoService::class)->causar([
            'tipo_gasto_id' => $otroTipoGasto->id,
            'tercero_id' => $tercero->id,
            'centro_costo_id' => $this->crearCentroCosto()->id,
            'fecha' => '2026-01-16',
            'numero_documento' => 'FC-011',
            'concepto' => 'Compra de insumos 2',
            'valor_bruto' => '500',
        ])->fresh(['documentoTercero']);

        $datos = $this->datosValidos($gastoUno);
        $datos['aplicaciones'] = [
            ['documento_tercero_id' => $gastoUno->documento_tercero_id, 'valor' => '1000'],
            ['documento_tercero_id' => $gastoDos->documento_tercero_id, 'valor' => '500'],
        ];

        $respuesta = $this->postJson('/api/v1/egresos', $datos)->assertStatus(201);
        $egreso = Egreso::findOrFail($respuesta->json('egreso.id'));

        $this->postJson($this->urlReversa($egreso), ['motivo' => self::MOTIVO])->assertOk();

        $documentoUno = DocumentoTercero::findOrFail($gastoUno->documento_tercero_id);
        $documentoDos = DocumentoTercero::findOrFail($gastoDos->documento_tercero_id);
        $this->assertEquals(1000, $documentoUno->saldo->aFloat());
        $this->assertEquals(500, $documentoDos->saldo->aFloat());
        $this->assertSame('pendiente', $documentoUno->estado->value);
        $this->assertSame('pendiente', $documentoDos->estado->value);
    }

    public function test_contable_con_el_permiso_de_reversa_puede_reversar(): void
    {
        $admin = $this->actingAsAdmin();
        $egreso = $this->crearEgreso();

        $rol = Rol::create(['empresa_id' => $admin->empresa_id, 'nombre' => 'contable']);
        $contable = Usuario::create([
            'empresa_id' => $admin->empresa_id,
            'nombre' => 'Contable de prueba',
            'usuario' => 'contable_test_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id,
            'activo' => true,
        ]);
        DB::table('rol_permiso')->insert([
            'rol_id' => $rol->id,
            'permiso_id' => DB::table('permisos')->where('modulo', 'egresos')->where('accion', 'reversar')->value('id'),
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);

        $this->actingAs($contable, 'sanctum');

        $this->postJson($this->urlReversa($egreso), ['motivo' => self::MOTIVO])->assertOk();
        $this->assertDatabaseHas('reversas_egreso', ['egreso_id' => $egreso->id]);
    }
}
