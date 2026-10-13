<?php

namespace Tests\Feature\Facturacion;

use App\Contabilidad\Models\TipoDocumentoNegocio;
use App\Creditos\Models\Credito;
use App\Empresas\Models\Empresa;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

class DocumentosFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion/documentos';

    private function credito(): Credito
    {
        $this->crearReglasFacturacion();

        return $this->crearCredito($this->crearCliente(['nombre' => 'Ana Gomez']));
    }

    /** @param list<string> $valores valores en pesos, uno por línea */
    private function lineas(array $valores = ['1000']): array
    {
        return array_map(fn (string $valor) => [
            'concepto' => 'INTERES.AMORT.ADM',
            'descripcion' => 'Interés administrativo',
            'valor' => $valor,
            'tipo_concepto' => 'otros_gastos',
        ], $valores);
    }

    private function borrador(Credito $credito, array $valores = ['1000']): array
    {
        return $this->postJson(self::BASE, ['cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id, 'lineas' => $this->lineas($valores)])
            ->assertStatus(201)
            ->json('documento');
    }

    /**
     * Aprueba una nota crédito pendiente con una persona distinta de quien la solicitó (7.1.2) — sin esto, la nota
     * se queda en `pendiente_aprobacion`, sin número ni efecto sobre el saldo de la factura. Deja al aprobador como
     * usuario autenticado: si el test sigue operando como el solicitante original, hay que volver a `actingAs()`.
     */
    private function aprobarNotaCredito(array $notaPendiente, Empresa $empresa): array
    {
        $this->actingAsRolConPermisos('aprobador_nc', [
            'facturacion.aprobar_nota_credito', 'facturacion.listar_facturas', 'facturacion.listar_notas_credito',
        ], $empresa);

        return $this->postJson(self::BASE.'/'.$notaPendiente['id'].'/aprobar')->assertOk()->json('documento');
    }

    private function factura(Credito $credito, array $valores = ['1000']): array
    {
        $borrador = $this->borrador($credito, $valores);

        return $this->postJson(self::BASE.'/'.$borrador['id'].'/emitir')->assertOk()->json('documento');
    }

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->getJson(self::BASE)->assertStatus(401);
        $this->postJson(self::BASE, [])->assertStatus(401);
    }

    public function test_asesor_sin_permiso_recibe_403(): void
    {
        $this->actingAsAsesor();

        $this->getJson(self::BASE)->assertStatus(403);
        $this->postJson(self::BASE, ['credito_id' => 1, 'lineas' => $this->lineas()])->assertStatus(403);
    }

    public function test_no_emite_una_factura_si_el_catalogo_desactiva_fv(): void
    {
        $admin = $this->actingAsAdmin();
        $credito = $this->credito();
        $borrador = $this->borrador($credito);

        TipoDocumentoNegocio::where('empresa_id', $admin->empresa_id)->where('codigo', 'FV')->update(['activo' => false]);

        $this->postJson(self::BASE.'/'.$borrador['id'].'/emitir')
            ->assertStatus(422)
            ->assertJsonValidationErrors('documento');
    }

    public function test_crea_un_borrador_desde_un_credito_desembolsado_sin_numero(): void
    {
        $this->actingAsAdmin();
        $credito = $this->credito();

        $borrador = $this->borrador($credito, ['1500.50', '200']);

        $this->assertSame('borrador', $borrador['estado']);
        $this->assertNull($borrador['numero']);
        $this->assertDatabaseHas('documentos_facturacion', [
            'id' => $borrador['id'], 'tipo' => 'factura', 'estado' => 'borrador', 'consecutivo' => null,
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
        ]);
        $this->assertDatabaseHas('lineas_documento_facturacion', ['documento_id' => $borrador['id'], 'valor' => 150050]);
        $this->assertCount(2, $borrador['lineas']);
    }

    public function test_rechaza_un_credito_inexistente_y_lineas_invalidas(): void
    {
        $this->actingAsAdmin();
        $credito = $this->credito();

        $this->postJson(self::BASE, ['cliente_id' => $credito->cliente_id, 'credito_id' => 9999, 'lineas' => $this->lineas()])->assertStatus(422)->assertJsonValidationErrors('credito_id');
        $this->postJson(self::BASE, ['cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id, 'lineas' => []])->assertStatus(422)->assertJsonValidationErrors('lineas');
        $this->postJson(self::BASE, ['cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id, 'lineas' => $this->lineas(['0'])])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lineas.0.valor');
    }

    public function test_emite_facturas_con_numeracion_consecutiva_sin_huecos(): void
    {
        $this->actingAsAdmin();
        $credito = $this->credito();

        $primera = $this->factura($credito);

        // Concepto distinto al de factura(): el mismo crédito ya factura ese concepto este mes (8.2), este test solo
        // quiere comprobar la numeración consecutiva, no el duplicado.
        $borradorSegunda = $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
            'lineas' => [['concepto' => 'OTROS.GASTOS.COBRANZA', 'descripcion' => 'Gastos de cobranza', 'valor' => '1000', 'tipo_concepto' => 'otros_gastos']],
        ])->assertStatus(201)->json('documento');
        $segunda = $this->postJson(self::BASE.'/'.$borradorSegunda['id'].'/emitir')->assertOk()->json('documento');

        $this->assertSame(1, $primera['consecutivo']);
        $this->assertSame('F 1', $primera['numero']);
        $this->assertSame(2, $segunda['consecutivo']);
        $this->assertSame('F 2', $segunda['numero']);
        $this->assertSame('emitido', $primera['estado']);
    }

    public function test_un_documento_emitido_no_se_edita_ni_se_borra_ni_se_vuelve_a_emitir(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito());

        $this->patchJson(self::BASE.'/'.$factura['id'], ['lineas' => $this->lineas()])->assertStatus(422)->assertJsonValidationErrors('documento');
        $this->deleteJson(self::BASE.'/'.$factura['id'])->assertStatus(422)->assertJsonValidationErrors('documento');
        $this->postJson(self::BASE.'/'.$factura['id'].'/emitir')->assertStatus(422)->assertJsonValidationErrors('documento');
    }

    public function test_un_borrador_se_edita_y_se_elimina(): void
    {
        $this->actingAsAdmin();
        $borrador = $this->borrador($this->credito(), ['1000', '500']);

        $this->patchJson(self::BASE.'/'.$borrador['id'], ['lineas' => $this->lineas(['750'])])
            ->assertOk()
            ->assertJsonPath('totales.total', '750.00');

        $this->deleteJson(self::BASE.'/'.$borrador['id'])->assertStatus(204);
        $this->assertSoftDeleted('documentos_facturacion', ['id' => $borrador['id']]);
    }

    public function test_nota_credito_no_acredita_mas_que_lo_que_queda_de_la_linea_y_anula_la_factura(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $admin = $this->actingAsAdmin($empresa);
        $factura = $this->factura($this->credito(), ['1000']);
        $lineaId = $factura['lineas'][0]['id'];

        $primera = $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'rebaja_descuento',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '600']],
        ])->assertStatus(201);

        $this->assertSame('pendiente_aprobacion', $primera->json('documento.estado'));
        $this->assertNull($primera->json('documento.numero'));

        $primeraAprobada = $this->aprobarNotaCredito($primera->json('documento'), $empresa);
        $this->assertSame('NC 1', $primeraAprobada['numero']);
        $this->actingAs($admin, 'sanctum');

        // Ya acreditados 600 de 1000: una segunda nota de 500 sobre la misma línea se pasaría del saldo que queda.
        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'ajuste_precio',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '500']],
        ])->assertStatus(422)->assertJsonValidationErrors('lineas');

        $tercera = $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'anulacion',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '400']],
        ])->assertStatus(201)->json('documento');

        $this->aprobarNotaCredito($tercera, $empresa);
        $this->actingAs($admin, 'sanctum');

        $detalle = $this->getJson(self::BASE.'/'.$factura['id'])->assertOk();

        $this->assertTrue($detalle->json('totales.anulada'));
        $this->assertSame('0.00', $detalle->json('totales.saldo'));
        $this->assertEventoAuditoria('anular', 'facturacion', 'documento_facturacion', $primeraAprobada['id']);
    }

    public function test_nota_credito_exige_una_linea_de_su_factura(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito());
        $otra = $this->factura($this->credito());

        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'devolucion',
            'lineas' => [['linea_origen_id' => $otra['lineas'][0]['id'], 'valor' => '100']],
        ])->assertStatus(422)->assertJsonValidationErrors('lineas');

        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'devolucion',
            'lineas' => [['valor' => '100']],
        ])->assertStatus(422)->assertJsonValidationErrors('lineas.0.linea_origen_id');
    }

    public function test_el_mismo_usuario_no_puede_aprobar_su_propia_nota_credito(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito(), ['1000']);
        $lineaId = $factura['lineas'][0]['id'];

        $pendiente = $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'devolucion',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '100']],
        ])->assertStatus(201)->json('documento');

        $this->postJson(self::BASE.'/'.$pendiente['id'].'/aprobar')
            ->assertStatus(422)
            ->assertJsonValidationErrors('documento');

        $this->assertDatabaseHas('documentos_facturacion', ['id' => $pendiente['id'], 'estado' => 'pendiente_aprobacion']);
    }

    public function test_sin_permiso_de_aprobar_no_puede_aprobar(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->actingAsAdmin($empresa);
        $factura = $this->factura($this->credito(), ['1000']);
        $lineaId = $factura['lineas'][0]['id'];

        $pendiente = $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'devolucion',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '100']],
        ])->assertStatus(201)->json('documento');

        $this->actingAsRolConPermisos('solo_lectura_nc', ['facturacion.listar_notas_credito'], $empresa);

        $this->postJson(self::BASE.'/'.$pendiente['id'].'/aprobar')->assertStatus(403);
    }

    public function test_no_se_puede_aprobar_dos_veces_la_misma_nota_credito(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->actingAsAdmin($empresa);
        $factura = $this->factura($this->credito(), ['1000']);
        $lineaId = $factura['lineas'][0]['id'];

        $pendiente = $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'devolucion',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '100']],
        ])->assertStatus(201)->json('documento');

        $this->aprobarNotaCredito($pendiente, $empresa);

        $this->postJson(self::BASE.'/'.$pendiente['id'].'/aprobar')
            ->assertStatus(422)
            ->assertJsonValidationErrors('documento');
    }

    public function test_nota_debito_sobre_la_factura_con_concepto_libre(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito(), ['1000']);

        $nota = $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo_codigo' => 'gastos_por_cobrar', 'fecha_vencimiento' => '2026-12-01',
            'lineas' => [['concepto' => 'MORA', 'descripcion' => 'Gastos de cobranza', 'valor' => '50']],
        ])->assertStatus(201);

        $this->assertSame('ND 1', $nota->json('documento.numero'));

        $detalle = $this->getJson(self::BASE.'/'.$factura['id'])->assertOk();
        $this->assertSame('1050.00', $detalle->json('totales.saldo'));
        $this->assertFalse($detalle->json('totales.anulada'));
    }

    public function test_nota_debito_no_tiene_tope_de_valor(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito(), ['1000']);

        // A diferencia de la nota crédito (limitada al valor de la factura), la nota débito no tiene tope: solo exige soporte.
        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo_codigo' => 'gastos_por_cobrar', 'fecha_vencimiento' => '2026-12-01',
            'lineas' => [['concepto' => 'MORA', 'descripcion' => 'Gastos de cobranza', 'valor' => '50000000']],
        ])->assertStatus(201);
    }

    public function test_el_pdf_solo_sale_de_un_documento_emitido(): void
    {
        $this->actingAsAdmin();
        $borrador = $this->borrador($this->credito());
        $factura = $this->factura($this->credito());

        $this->get(self::BASE.'/'.$borrador['id'].'/pdf')->assertStatus(422);

        $respuesta = $this->get(self::BASE.'/'.$factura['id'].'/pdf')->assertOk();
        $this->assertSame('application/pdf', $respuesta->headers->get('Content-Type'));
    }

    public function test_motivo_fuera_del_catalogo_de_su_tipo_se_rechaza(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito(), ['1000']);
        $lineaId = $factura['lineas'][0]['id'];

        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'inventado',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '100']],
        ])->assertStatus(422)->assertJsonValidationErrors('motivo_codigo');

        // El catálogo de nota crédito no es válido para una nota débito (ni viceversa).
        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo_codigo' => 'devolucion',
            'lineas' => [['concepto' => 'MORA', 'descripcion' => 'Gastos', 'valor' => '100']],
        ])->assertStatus(422)->assertJsonValidationErrors('motivo_codigo');
    }

    public function test_motivo_otros_exige_detalle_y_queda_guardado_como_motivo(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito(), ['1000']);
        $lineaId = $factura['lineas'][0]['id'];

        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'otros',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '100']],
        ])->assertStatus(422)->assertJsonValidationErrors('motivo_detalle');

        $nota = $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'otros', 'motivo_detalle' => 'Corrección especial acordada con el cliente',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '100']],
        ])->assertStatus(201);

        $this->assertDatabaseHas('documentos_facturacion', [
            'id' => $nota->json('documento.id'), 'motivo' => 'Corrección especial acordada con el cliente',
        ]);
    }

    public function test_nota_credito_sobre_factura_ya_pagada_deja_saldo_a_favor_en_vez_de_bloquear(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $admin = $this->actingAsAdmin($empresa);
        $factura = $this->factura($this->credito(), ['1000']);
        $lineaId = $factura['lineas'][0]['id'];

        $this->postJson(self::BASE.'/'.$factura['id'].'/cobros', ['fecha' => now()->toDateString(), 'valor' => '1000', 'medio' => 'transferencia'])
            ->assertCreated();

        $pendiente = $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'devolucion',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '300']],
        ])->assertStatus(201)->json('documento');

        $this->aprobarNotaCredito($pendiente, $empresa);
        $this->actingAs($admin, 'sanctum');

        $detalle = $this->getJson(self::BASE.'/'.$factura['id'])->assertOk();

        $this->assertSame('con_saldo_a_favor', $detalle->json('totales.situacion'));
        $this->assertSame('-300.00', $detalle->json('totales.saldo'));
        $this->assertSame('300.00', $detalle->json('totales.saldo_a_favor'));
    }

    public function test_nota_debito_guarda_su_propia_fecha_de_vencimiento(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito(), ['1000']);

        $nota = $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo_codigo' => 'gastos_por_cobrar', 'fecha_vencimiento' => '2026-12-01',
            'lineas' => [['concepto' => 'MORA', 'descripcion' => 'Gastos de cobranza', 'valor' => '50']],
        ])->assertStatus(201);

        $this->assertDatabaseHas('documentos_facturacion', [
            'id' => $nota->json('documento.id'), 'fecha_vencimiento' => '2026-12-01',
        ]);
    }

    public function test_nota_debito_por_intereses_exige_y_guarda_el_soporte_del_calculo_de_mora(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito(), ['1000']);

        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo_codigo' => 'intereses',
            'lineas' => [['concepto' => 'MORA', 'descripcion' => 'Intereses de mora', 'valor' => '50']],
        ])->assertStatus(422)->assertJsonValidationErrors(['lineas.0.tasa_mora', 'lineas.0.dias_mora', 'lineas.0.base_mora']);

        $nota = $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo_codigo' => 'intereses', 'fecha_vencimiento' => '2026-12-01',
            'lineas' => [[
                'concepto' => 'MORA', 'descripcion' => 'Intereses de mora', 'valor' => '50',
                'tasa_mora' => '2.5', 'dias_mora' => 20, 'base_mora' => '1000',
            ]],
        ])->assertStatus(201);

        $this->assertDatabaseHas('lineas_documento_facturacion', [
            'documento_id' => $nota->json('documento.id'), 'tasa_mora' => 2.5, 'dias_mora' => 20, 'base_mora' => 100000,
        ]);
    }

    public function test_permiso_de_nota_credito_y_nota_debito_son_independientes(): void
    {
        $admin = $this->actingAsAdmin();
        $factura = $this->factura($this->credito(), ['1000']);
        $lineaId = $factura['lineas'][0]['id'];

        $rol = Rol::create(['empresa_id' => $admin->empresa_id, 'nombre' => 'facturador']);
        $usuario = Usuario::create([
            'empresa_id' => $admin->empresa_id,
            'nombre' => 'Facturador de prueba',
            'usuario' => 'facturador_test_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id,
            'activo' => true,
        ]);
        DB::table('rol_permiso')->insert([
            'rol_id' => $rol->id,
            'permiso_id' => DB::table('permisos')->where('modulo', 'facturacion')->where('accion', 'emitir_nota_credito')->value('id'),
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);

        $this->actingAs($usuario, 'sanctum');

        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'devolucion',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '100']],
        ])->assertStatus(201);

        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo_codigo' => 'gastos_por_cobrar', 'fecha_vencimiento' => '2026-12-01',
            'lineas' => [['concepto' => 'MORA', 'descripcion' => 'Gastos', 'valor' => '50']],
        ])->assertStatus(403);
    }

    public function test_el_listado_se_filtra_por_fecha_factura_origen_y_motivo(): void
    {
        $this->actingAsAdmin();

        $creditoEnero = $this->credito();
        $borradorEnero = $this->postJson(self::BASE, ['fecha' => '2026-01-10', 'cliente_id' => $creditoEnero->cliente_id, 'credito_id' => $creditoEnero->id, 'lineas' => $this->lineas()])
            ->assertCreated()->json('documento');
        $facturaEnero = $this->postJson(self::BASE.'/'.$borradorEnero['id'].'/emitir')->assertOk()->json('documento');

        $creditoFebrero = $this->credito();
        $borradorFebrero = $this->postJson(self::BASE, ['fecha' => '2026-02-20', 'cliente_id' => $creditoFebrero->cliente_id, 'credito_id' => $creditoFebrero->id, 'lineas' => $this->lineas()])
            ->assertCreated()->json('documento');
        $facturaFebrero = $this->postJson(self::BASE.'/'.$borradorFebrero['id'].'/emitir')->assertOk()->json('documento');

        $notaSobreEnero = $this->postJson(self::BASE.'/'.$facturaEnero['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'ajuste_precio', 'motivo_detalle' => null,
            'lineas' => [['linea_origen_id' => $facturaEnero['lineas'][0]['id'], 'valor' => '100']],
        ])->assertStatus(201)->json('documento');

        $this->postJson(self::BASE.'/'.$facturaFebrero['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'devolucion', 'motivo_detalle' => null,
            'lineas' => [['linea_origen_id' => $facturaFebrero['lineas'][0]['id'], 'valor' => '100']],
        ])->assertStatus(201);

        $porFecha = $this->getJson(self::BASE.'?tipo=factura&desde=2026-02-01')->assertOk();
        $this->assertSame([$facturaFebrero['id']], collect($porFecha->json('data'))->pluck('id')->all());

        $porFactura = $this->getJson(self::BASE.'?tipo=nota_credito&factura='.urlencode($facturaEnero['numero']))->assertOk();
        $this->assertSame([$notaSobreEnero['id']], collect($porFactura->json('data'))->pluck('id')->all());

        $porMotivo = $this->getJson(self::BASE.'?tipo=nota_credito&motivo_codigo=devolucion')->assertOk();
        $this->assertSame('Devolución', collect($porMotivo->json('data'))->first()['motivo']);
        $this->assertCount(1, $porMotivo->json('data'));
    }

    public function test_un_documento_de_otra_empresa_responde_404(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito());

        $this->actingAsAdmin();

        $this->getJson(self::BASE.'/'.$factura['id'])->assertStatus(404);
        $this->postJson(self::BASE.'/'.$factura['id'].'/emitir')->assertStatus(404);
        $this->get(self::BASE.'/'.$factura['id'].'/pdf')->assertStatus(404);
    }
}
