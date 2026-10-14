<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Models\DocumentoTercero;
use App\Contabilidad\Models\PlanCuenta;
use App\Empresas\Models\Empresa;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

/** Comprobante manual ↔ motor DocumentoTercero: causar una CxP/CxC con documento cruce y cruzarla luego con un pago. */
class ComprobanteManualDocumentoTerceroTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/contabilidad/comprobantes';

    /** @var array<int, Usuario> Un revisor por empresa: cada comprobante de la misma empresa lo reutiliza en vez de recrear el rol (nombre único por empresa). */
    private array $revisores = [];

    private function creador(Empresa $empresa): Usuario
    {
        return $this->actingAsRolConPermisos(
            'creador',
            ['contabilidad.crear_manual', 'contabilidad.revisar', 'contabilidad.contabilizar', 'contabilidad.listar_comprobantes', 'contabilidad.anular'],
            $empresa,
        );
    }

    private function revisor(Empresa $empresa): void
    {
        $usuario = $this->revisores[$empresa->id] ??= $this->actingAsRolConPermisos(
            'revisor',
            ['contabilidad.revisar', 'contabilidad.contabilizar', 'contabilidad.listar_comprobantes'],
            $empresa,
        );

        $this->actingAs($usuario, 'sanctum');
    }

    /** Cuenta de proveedores: pasivo, crédito aumenta el saldo (causa), débito lo cruza (paga). */
    private function cuentaCxp(): PlanCuenta
    {
        return $this->crearCuenta([
            'tipo' => TipoCuenta::Pasivo,
            'naturaleza' => NaturalezaCuenta::Credito,
            'exige_tercero' => true,
            'exige_documento_cruce' => true,
        ]);
    }

    /** Crea un comprobante manual con 2 líneas y lo lleva hasta contabilizado (creador -> revisor -> contabilizar). */
    private function contabilizarComprobante(array $payload, Empresa $empresa): int
    {
        $comprobanteId = $this->postJson(self::BASE, $payload)->assertStatus(201)->json('comprobante.id');

        $this->revisor($empresa);
        $this->postJson(self::BASE."/{$comprobanteId}/revisar")->assertOk();
        $this->postJson(self::BASE."/{$comprobanteId}/contabilizar")->assertOk();

        return $comprobanteId;
    }

    public function test_causar_una_cxp_con_documento_cruce_crea_el_documento_tercero_al_contabilizar(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->creador($empresa);
        $cuentaCxp = $this->cuentaCxp();
        $cuentaGasto = $this->crearCuenta();
        $tercero = $this->crearTercero();

        $payload = [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Honorarios enero',
            'lineas' => [
                ['cuenta_id' => $cuentaGasto->id, 'debito' => 100, 'credito' => 0],
                [
                    'cuenta_id' => $cuentaCxp->id, 'debito' => 0, 'credito' => 100,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'FE-4587', 'fecha_vencimiento' => '2026-02-15',
                ],
            ],
        ];

        $comprobanteId = $this->postJson(self::BASE, $payload)->assertStatus(201)->json('comprobante.id');

        $this->assertDatabaseMissing('documentos_tercero', ['numero_documento' => 'FE-4587']);

        $this->revisor($empresa);
        $this->postJson(self::BASE."/{$comprobanteId}/revisar")->assertOk();
        $this->postJson(self::BASE."/{$comprobanteId}/contabilizar")->assertOk();

        $this->assertDatabaseHas('documentos_tercero', [
            'tercero_id' => $tercero->id,
            'naturaleza' => 'por_pagar',
            'numero_documento' => 'FE-4587',
            'valor_original' => 10000,
            'saldo' => 10000,
            'estado' => 'pendiente',
            'modulo_origen' => 'contabilidad_manual',
            'origen_id' => $comprobanteId,
        ]);
    }

    public function test_pagar_con_el_mismo_documento_cruce_cruza_el_documento_y_lo_cancela(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $admin = $this->creador($empresa);
        $cuentaCxp = $this->cuentaCxp();
        $cuentaBanco = $this->crearCuenta();
        $tercero = $this->crearTercero();

        $this->contabilizarComprobante([
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Honorarios enero',
            'lineas' => [
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => 100, 'credito' => 0],
                [
                    'cuenta_id' => $cuentaCxp->id, 'debito' => 0, 'credito' => 100,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'FE-4587',
                ],
            ],
        ], $empresa);

        $this->actingAs($admin, 'sanctum');
        $comprobanteId = $this->contabilizarComprobante([
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-02-01',
            'concepto' => 'Pago honorarios',
            'lineas' => [
                [
                    'cuenta_id' => $cuentaCxp->id, 'debito' => 100, 'credito' => 0,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'FE-4587',
                ],
                ['cuenta_id' => $cuentaBanco->id, 'debito' => 0, 'credito' => 100],
            ],
        ], $empresa);

        $this->assertDatabaseHas('documentos_tercero', [
            'numero_documento' => 'FE-4587',
            'saldo' => 0,
            'estado' => 'cancelado',
        ]);
        $this->assertDatabaseHas('aplicaciones_documento', [
            'modulo_aplicacion' => 'contabilidad_manual',
            'aplicacion_id' => $comprobanteId,
            'valor_aplicado' => 10000,
        ]);
    }

    public function test_pagar_parcialmente_deja_el_documento_abonado_parcial(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $admin = $this->creador($empresa);
        $cuentaCxp = $this->cuentaCxp();
        $tercero = $this->crearTercero();

        $this->contabilizarComprobante([
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Honorarios enero',
            'lineas' => [
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => 100, 'credito' => 0],
                [
                    'cuenta_id' => $cuentaCxp->id, 'debito' => 0, 'credito' => 100,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'FE-4587',
                ],
            ],
        ], $empresa);

        $this->actingAs($admin, 'sanctum');
        $this->contabilizarComprobante([
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-02-01',
            'concepto' => 'Abono parcial',
            'lineas' => [
                [
                    'cuenta_id' => $cuentaCxp->id, 'debito' => 60, 'credito' => 0,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'FE-4587',
                ],
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => 0, 'credito' => 60],
            ],
        ], $empresa);

        $this->assertDatabaseHas('documentos_tercero', [
            'numero_documento' => 'FE-4587',
            'saldo' => 4000,
            'estado' => 'abonado_parcial',
        ]);
    }

    public function test_causar_dos_veces_el_mismo_documento_cruce_falla(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $admin = $this->creador($empresa);
        $cuentaCxp = $this->cuentaCxp();
        $tercero = $this->crearTercero();

        $payload = fn () => [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Honorarios enero',
            'lineas' => [
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => 100, 'credito' => 0],
                [
                    'cuenta_id' => $cuentaCxp->id, 'debito' => 0, 'credito' => 100,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'FE-4587',
                ],
            ],
        ];

        $this->contabilizarComprobante($payload(), $empresa);

        $this->actingAs($admin, 'sanctum');
        $comprobanteId = $this->postJson(self::BASE, $payload())->assertStatus(201)->json('comprobante.id');
        $this->revisor($empresa);
        $this->postJson(self::BASE."/{$comprobanteId}/revisar")->assertOk();

        $this->postJson(self::BASE."/{$comprobanteId}/contabilizar")
            ->assertStatus(422)
            ->assertJsonValidationErrors('comprobante');
    }

    public function test_pagar_un_documento_cruce_que_no_existe_falla(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->creador($empresa);
        $cuentaCxp = $this->cuentaCxp();
        $cuentaBanco = $this->crearCuenta();
        $tercero = $this->crearTercero();

        $comprobanteId = $this->postJson(self::BASE, [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Pago sin causación previa',
            'lineas' => [
                [
                    'cuenta_id' => $cuentaCxp->id, 'debito' => 100, 'credito' => 0,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'FE-9999',
                ],
                ['cuenta_id' => $cuentaBanco->id, 'debito' => 0, 'credito' => 100],
            ],
        ])->assertStatus(201)->json('comprobante.id');

        $this->revisor($empresa);
        $this->postJson(self::BASE."/{$comprobanteId}/revisar")->assertOk();

        $this->postJson(self::BASE."/{$comprobanteId}/contabilizar")
            ->assertStatus(422)
            ->assertJsonValidationErrors('comprobante');
    }

    public function test_una_cuenta_no_apta_para_documento_cruce_falla_al_contabilizar(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->creador($empresa);
        $cuentaInapta = $this->crearCuenta([
            'tipo' => TipoCuenta::Gasto,
            'naturaleza' => NaturalezaCuenta::Debito,
            'exige_tercero' => true,
            'exige_documento_cruce' => true,
        ]);
        $cuentaBanco = $this->crearCuenta();
        $tercero = $this->crearTercero();

        $comprobanteId = $this->postJson(self::BASE, [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Configuración inusual',
            'lineas' => [
                [
                    'cuenta_id' => $cuentaInapta->id, 'debito' => 100, 'credito' => 0,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'FE-1000',
                ],
                ['cuenta_id' => $cuentaBanco->id, 'debito' => 0, 'credito' => 100],
            ],
        ])->assertStatus(201)->json('comprobante.id');

        $this->revisor($empresa);
        $this->postJson(self::BASE."/{$comprobanteId}/revisar")->assertOk();

        $this->postJson(self::BASE."/{$comprobanteId}/contabilizar")
            ->assertStatus(422)
            ->assertJsonValidationErrors('comprobante');
    }

    public function test_reversar_una_causacion_sin_pagos_anula_el_documento_tercero(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $admin = $this->creador($empresa);
        $cuentaCxp = $this->cuentaCxp();
        $tercero = $this->crearTercero();

        $comprobanteId = $this->contabilizarComprobante([
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Honorarios enero',
            'lineas' => [
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => 100, 'credito' => 0],
                [
                    'cuenta_id' => $cuentaCxp->id, 'debito' => 0, 'credito' => 100,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'FE-4587',
                ],
            ],
        ], $empresa);

        $documento = DocumentoTercero::where('numero_documento', 'FE-4587')->firstOrFail();

        $this->actingAs($admin, 'sanctum');
        $this->postJson(self::BASE."/{$comprobanteId}/reversar", ['motivo' => 'Causación duplicada por error'])
            ->assertStatus(201);

        $this->assertDatabaseHas('documentos_tercero', ['id' => $documento->id, 'estado' => 'anulado', 'borrado_en' => null]);
    }

    public function test_no_se_puede_reversar_una_causacion_que_ya_tiene_un_pago_de_otro_comprobante(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $admin = $this->creador($empresa);
        $cuentaCxp = $this->cuentaCxp();
        $cuentaBanco = $this->crearCuenta();
        $tercero = $this->crearTercero();

        $causacionId = $this->contabilizarComprobante([
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Honorarios enero',
            'lineas' => [
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => 100, 'credito' => 0],
                [
                    'cuenta_id' => $cuentaCxp->id, 'debito' => 0, 'credito' => 100,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'FE-4587',
                ],
            ],
        ], $empresa);

        $this->actingAs($admin, 'sanctum');
        $this->contabilizarComprobante([
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-02-01',
            'concepto' => 'Pago honorarios',
            'lineas' => [
                [
                    'cuenta_id' => $cuentaCxp->id, 'debito' => 100, 'credito' => 0,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'FE-4587',
                ],
                ['cuenta_id' => $cuentaBanco->id, 'debito' => 0, 'credito' => 100],
            ],
        ], $empresa);

        $this->actingAs($admin, 'sanctum');
        $this->postJson(self::BASE."/{$causacionId}/reversar", ['motivo' => 'Intento de reversa bloqueado'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('comprobante');
    }

    public function test_reversar_un_pago_restaura_el_saldo_del_documento_original(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $admin = $this->creador($empresa);
        $cuentaCxp = $this->cuentaCxp();
        $cuentaBanco = $this->crearCuenta();
        $tercero = $this->crearTercero();

        $this->contabilizarComprobante([
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Honorarios enero',
            'lineas' => [
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => 100, 'credito' => 0],
                [
                    'cuenta_id' => $cuentaCxp->id, 'debito' => 0, 'credito' => 100,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'FE-4587',
                ],
            ],
        ], $empresa);

        $this->actingAs($admin, 'sanctum');
        $pagoId = $this->contabilizarComprobante([
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-02-01',
            'concepto' => 'Pago honorarios',
            'lineas' => [
                [
                    'cuenta_id' => $cuentaCxp->id, 'debito' => 100, 'credito' => 0,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'FE-4587',
                ],
                ['cuenta_id' => $cuentaBanco->id, 'debito' => 0, 'credito' => 100],
            ],
        ], $empresa);

        $this->assertDatabaseHas('documentos_tercero', ['numero_documento' => 'FE-4587', 'saldo' => 0, 'estado' => 'cancelado']);

        $this->actingAs($admin, 'sanctum');
        $this->postJson(self::BASE."/{$pagoId}/reversar", ['motivo' => 'Pago duplicado por error'])->assertStatus(201);

        $this->assertDatabaseHas('documentos_tercero', ['numero_documento' => 'FE-4587', 'saldo' => 10000, 'estado' => 'pendiente']);
    }

    public function test_no_encuentra_un_documento_cruce_de_otra_empresa_con_el_mismo_numero(): void
    {
        $empresaAjena = $this->crearEmpresaDePrueba();
        $this->creador($empresaAjena);
        $cuentaCxpAjena = $this->cuentaCxp();
        $terceroAjeno = $this->crearTercero();

        $this->contabilizarComprobante([
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Honorarios de otra empresa',
            'lineas' => [
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => 100, 'credito' => 0],
                [
                    'cuenta_id' => $cuentaCxpAjena->id, 'debito' => 0, 'credito' => 100,
                    'tercero_id' => $terceroAjeno->id, 'documento_cruce' => 'FE-4587',
                ],
            ],
        ], $empresaAjena);

        $empresa = $this->crearEmpresaDePrueba();
        $this->creador($empresa);
        $cuentaCxp = $this->cuentaCxp();
        $cuentaBanco = $this->crearCuenta();
        $tercero = $this->crearTercero();

        $comprobanteId = $this->postJson(self::BASE, [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-20',
            'concepto' => 'Pago con número coincidente de otra empresa',
            'lineas' => [
                [
                    'cuenta_id' => $cuentaCxp->id, 'debito' => 100, 'credito' => 0,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'FE-4587',
                ],
                ['cuenta_id' => $cuentaBanco->id, 'debito' => 0, 'credito' => 100],
            ],
        ])->assertStatus(201)->json('comprobante.id');

        $this->revisor($empresa);
        $this->postJson(self::BASE."/{$comprobanteId}/revisar")->assertOk();

        $this->postJson(self::BASE."/{$comprobanteId}/contabilizar")
            ->assertStatus(422)
            ->assertJsonValidationErrors('comprobante');
    }
}
