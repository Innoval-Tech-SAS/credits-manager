<?php

namespace Tests\Feature\Auditoria;

use App\Contabilidad\Enums\EstadoPeriodo;
use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Models\PeriodoContable;
use App\Contabilidad\Services\DocumentoTerceroService;
use App\Core\Dinero\Dinero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class TrazabilidadContabilidadTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private function payloadComprobante(): array
    {
        return [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Comprobante manual',
            'lineas' => [
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => 0, 'credito' => 100],
            ],
        ];
    }

    public function test_un_comprobante_manual_deja_un_evento_de_contabilizacion_con_sus_totales(): void
    {
        $admin = $this->actingAsAdmin();

        $id = $this->postJson('/api/v1/contabilidad/comprobantes', $this->payloadComprobante())->assertCreated()->json('comprobante.id');

        $evento = $this->assertEventoAuditoria('contabilizar', 'contabilidad', 'comprobante', $id);
        $this->assertSame($admin->empresa_id, $evento['empresa_id']);
        $this->assertSame('100.00', $evento['valores_nuevos']['total_debito']);
        $this->assertSame('100.00', $evento['valores_nuevos']['total_credito']);
        $this->assertSame(2, $evento['valores_nuevos']['lineas']);
        $this->assertSame('contabilizado', $evento['valores_nuevos']['estado']);
        $this->assertStringContainsString('(origen: manual)', $evento['descripcion']);
        $this->assertStringNotContainsString('#)', $evento['descripcion']);
    }

    public function test_un_comprobante_descuadrado_no_deja_evento(): void
    {
        $this->actingAsAdmin();
        $payload = $this->payloadComprobante();
        $payload['lineas'][1]['credito'] = 50;

        $this->postJson('/api/v1/contabilidad/comprobantes', $payload)->assertStatus(422);

        $this->assertSinEventosDeAuditoria();
    }

    public function test_reversar_un_comprobante_deja_el_motivo_y_el_comprobante_de_reverso(): void
    {
        $this->actingAsAdmin();
        $originalId = $this->postJson('/api/v1/contabilidad/comprobantes', $this->payloadComprobante())->json('comprobante.id');

        $this->postJson("/api/v1/contabilidad/comprobantes/{$originalId}/reversar", ['motivo' => 'Error de digitación'])->assertCreated();

        $reversion = $this->assertEventoAuditoria('reversar', 'contabilidad', 'comprobante', $originalId);
        $this->assertSame('Error de digitación', $reversion['motivo']);
        $this->assertSame('contabilizado', $reversion['valores_anteriores']['estado']);
        $this->assertSame('anulado', $reversion['valores_nuevos']['estado']);
        $this->assertNotNull($reversion['valores_nuevos']['comprobante_reverso_id']);
        $this->assertCount(2, $this->eventosDeAuditoria('contabilizar', 'contabilidad'));
    }

    public function test_cerrar_y_reabrir_un_periodo_dejan_su_evento_con_el_motivo(): void
    {
        $this->actingAsAdmin();
        $periodo = PeriodoContable::create(['anio' => 2026, 'mes' => 1, 'estado' => EstadoPeriodo::Abierto]);

        $this->postJson("/api/v1/contabilidad/periodos/{$periodo->id}/cerrar")->assertOk();
        $this->postJson("/api/v1/contabilidad/periodos/{$periodo->id}/reabrir", ['motivo' => 'Ajuste autorizado'])->assertOk();

        $cierre = $this->assertEventoAuditoria('cerrar_periodo', 'contabilidad', 'periodo_contable', $periodo->id);
        $this->assertSame('abierto', $cierre['valores_anteriores']['estado']);
        $this->assertSame('cerrado', $cierre['valores_nuevos']['estado']);

        $reapertura = $this->assertEventoAuditoria('reabrir_periodo', 'contabilidad', 'periodo_contable', $periodo->id);
        $this->assertSame('Ajuste autorizado', $reapertura['motivo']);
        $this->assertSame('cerrado', $reapertura['valores_anteriores']['estado']);
        $this->assertSame('abierto', $reapertura['valores_nuevos']['estado']);
    }

    public function test_una_reapertura_sin_motivo_no_deja_evento(): void
    {
        $this->actingAsAdmin();
        $periodo = PeriodoContable::create(['anio' => 2026, 'mes' => 1, 'estado' => EstadoPeriodo::Cerrado]);

        $this->postJson("/api/v1/contabilidad/periodos/{$periodo->id}/reabrir", [])->assertStatus(422);

        $this->assertSinEventosDeAuditoria();
    }

    public function test_crear_y_editar_una_cuenta_dejan_eventos_y_editar_sin_cambios_no(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuenta();

        $id = $this->postJson('/api/v1/contabilidad/plan-cuentas', [
            'codigo' => '1105', 'nombre' => 'Caja general', 'naturaleza' => 'debito', 'tipo' => 'activo', 'permite_movimiento' => true,
        ])->assertCreated()->json('cuenta.id');
        $creacion = $this->assertEventoAuditoria('crear', 'contabilidad', 'plan_cuenta', $id);
        $this->assertSame('1105', $creacion['valores_nuevos']['codigo']);

        $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", ['activa' => false])->assertOk();
        $edicion = $this->assertEventoAuditoria('modificar', 'contabilidad', 'plan_cuenta', $cuenta->id);
        $this->assertSame(['activa' => true], $edicion['valores_anteriores']);
        $this->assertSame(['activa' => false], $edicion['valores_nuevos']);

        $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", ['activa' => false])->assertOk();
        $this->assertCount(1, $this->eventosDeAuditoria('modificar', 'contabilidad'), 'repetir el mismo valor no es un cambio');
    }

    public function test_tipos_de_comprobante_y_centros_de_costo_dejan_evento(): void
    {
        $this->actingAsAdmin();

        $tipoId = $this->postJson('/api/v1/contabilidad/tipos-comprobante', ['codigo' => 'CE', 'nombre' => 'Comprobante de egreso'])->assertCreated()->json('tipo.id');
        $centroId = $this->postJson('/api/v1/contabilidad/centros-costo', ['codigo' => 'ADM', 'nombre' => 'Administración'])->assertCreated()->json('centro.id');
        $this->patchJson("/api/v1/contabilidad/centros-costo/{$centroId}", ['nombre' => 'Administración central'])->assertOk();

        $this->assertEventoAuditoria('crear', 'contabilidad', 'tipo_comprobante', $tipoId);
        $this->assertEventoAuditoria('crear', 'contabilidad', 'centro_costo', $centroId);
        $edicion = $this->assertEventoAuditoria('modificar', 'contabilidad', 'centro_costo', $centroId);
        $this->assertSame('Administración', $edicion['valores_anteriores']['nombre']);
        $this->assertSame('Administración central', $edicion['valores_nuevos']['nombre']);
    }

    public function test_guardar_una_regla_de_contabilizacion_deja_creacion_y_luego_cambios(): void
    {
        $this->actingAsAdmin();
        $primera = $this->crearCuenta();
        $segunda = $this->crearCuenta();
        $tipo = $this->crearTipoComprobante();
        $payload = ['evento' => EventoContable::DesembolsoCredito->value, 'cuenta_debito_id' => $primera->id, 'cuenta_credito_id' => $segunda->id, 'tipo_comprobante_id' => $tipo->id];

        $this->postJson('/api/v1/contabilidad/reglas', $payload)->assertSuccessful();
        $this->postJson('/api/v1/contabilidad/reglas', $payload)->assertSuccessful();
        $this->postJson('/api/v1/contabilidad/reglas', [...$payload, 'cuenta_debito_id' => $segunda->id, 'cuenta_credito_id' => $primera->id])->assertSuccessful();

        $this->assertCount(1, $this->eventosDeAuditoria('crear', 'contabilidad'), 'solo la primera vez se crea');
        $cambio = $this->eventosDeAuditoria('modificar', 'contabilidad');
        $this->assertCount(1, $cambio, 'guardar lo mismo no es un cambio');
        $this->assertSame($primera->id, $cambio[0]['valores_anteriores']['cuenta_debito_id']);
        $this->assertSame($segunda->id, $cambio[0]['valores_nuevos']['cuenta_debito_id']);
    }

    public function test_los_documentos_de_terceros_dejan_evento_al_crearse_aplicarse_y_anularse(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $servicio = app(DocumentoTerceroService::class);

        $documento = $servicio->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura_compra', 'FC-001', '2026-01-15', 'Compra', Dinero::desdePesos(1000), 'facturas', 1);
        $servicio->aplicarValor($tercero, NaturalezaDocumento::PorPagar, [['documento_tercero_id' => $documento->id, 'valor' => 400]], 'egresos', 7);
        $otro = $servicio->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura_compra', 'FC-002', '2026-01-16', 'Compra', Dinero::desdePesos(500), 'facturas', 2);
        $servicio->anular($otro);

        $this->assertCount(2, $this->eventosDeAuditoria('crear', 'contabilidad'));
        $aplicacion = $this->assertEventoAuditoria('modificar', 'contabilidad', 'documento_tercero', $documento->id);
        $this->assertSame('1000.00', $aplicacion['valores_anteriores']['saldo']);
        $this->assertSame('600.00', $aplicacion['valores_nuevos']['saldo']);
        $this->assertSame('400.00', $aplicacion['valores_nuevos']['valor_aplicado']);
        $this->assertSame(7, $aplicacion['valores_nuevos']['aplicacion_id']);
        $this->assertEventoAuditoria('anular', 'contabilidad', 'documento_tercero', $otro->id);
    }

    public function test_importar_una_migracion_de_apertura_deja_el_resumen_y_los_primeros_errores(): void
    {
        $this->actingAsAdmin();
        $csv = "codigo,nombre,naturaleza,tipo\n1105,Caja,debito,activo\n2205\n";

        $id = $this->postJson('/api/v1/contabilidad/migraciones-apertura', [
            'tipo' => 'plan_cuentas',
            'archivo' => UploadedFile::fake()->createWithContent('cuentas.csv', $csv),
        ])->assertCreated()->json('migracion.id');

        $evento = $this->assertEventoAuditoria('importar', 'contabilidad', 'migracion_apertura', $id);
        $this->assertSame('plan_cuentas', $evento['valores_nuevos']['tipo']);
        $this->assertSame(1, $evento['valores_nuevos']['registros_aceptados']);
        $this->assertSame(1, $evento['valores_nuevos']['registros_rechazados']);
        $this->assertCount(1, $evento['valores_nuevos']['primeros_errores']);
        $this->assertSame('cuentas.csv', $evento['valores_nuevos']['archivo_original']);
    }
}
