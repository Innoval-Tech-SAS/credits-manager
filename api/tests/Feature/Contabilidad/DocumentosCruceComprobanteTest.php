<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\EstadoDocumentoTercero;
use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Models\DocumentoTercero;
use App\Contabilidad\Models\PlanCuenta;
use App\Core\Dinero\Dinero;
use App\Empresas\Models\Empresa;
use App\Terceros\Models\Tercero;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

/** Saldos vivos que el comprobante manual ofrece como documento cruce: pendientes, abonados, vencidos y anticipos. */
class DocumentosCruceComprobanteTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/contabilidad/comprobantes';

    private function creador(Empresa $empresa): Usuario
    {
        return $this->actingAsRolConPermisos(
            'creador',
            ['contabilidad.crear_manual', 'contabilidad.revisar', 'contabilidad.contabilizar', 'contabilidad.listar_comprobantes'],
            $empresa,
        );
    }

    private function cuentaAnticipos(): PlanCuenta
    {
        return $this->crearCuenta([
            'tipo' => TipoCuenta::Activo,
            'naturaleza' => NaturalezaCuenta::Debito,
            'exige_tercero' => true,
            'exige_documento_cruce' => true,
        ]);
    }

    private function crearDocumento(Tercero $tercero, ?PlanCuenta $cuenta, string $numero, array $overrides = []): DocumentoTercero
    {
        return DocumentoTercero::create(array_merge([
            'tercero_id' => $tercero->id,
            'naturaleza' => NaturalezaDocumento::PorCobrar,
            'plan_cuenta_id' => $cuenta?->id,
            'tipo_documento' => 'CC',
            'numero_documento' => $numero,
            'fecha' => '2026-01-10',
            'concepto' => "Documento {$numero}",
            'valor_original' => Dinero::desdePesos('100'),
            'saldo' => Dinero::desdePesos('100'),
            'estado' => EstadoDocumentoTercero::Pendiente,
            'modulo_origen' => 'contabilidad_manual',
            'origen_id' => 1,
        ], $overrides));
    }

    public function test_sin_autenticar_responde_401(): void
    {
        $this->getJson(self::BASE.'/documentos-cruce?cuenta_id=1&tercero_id=1')->assertStatus(401);
    }

    public function test_sin_permiso_de_crear_comprobantes_responde_403(): void
    {
        $this->actingAsRolConPermisos('lector', ['contabilidad.listar_comprobantes'], $this->crearEmpresaDePrueba());

        $this->getJson(self::BASE.'/documentos-cruce?cuenta_id=1&tercero_id=1')->assertStatus(403);
    }

    public function test_exige_cuenta_y_tercero(): void
    {
        $this->creador($this->crearEmpresaDePrueba());

        $this->getJson(self::BASE.'/documentos-cruce')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cuenta_id', 'tercero_id']);
    }

    public function test_lista_los_saldos_vivos_del_tercero_en_la_cuenta_incluidos_vencidos_abonados_y_anticipos(): void
    {
        $this->creador($this->crearEmpresaDePrueba());
        $cuenta = $this->cuentaAnticipos();
        $otraCuenta = $this->cuentaAnticipos();
        $tercero = $this->crearTercero();
        $otroTercero = $this->crearTercero();

        $this->crearDocumento($tercero, $cuenta, 'PEND-1');
        $this->crearDocumento($tercero, $cuenta, 'VENC-1', ['fecha_vencimiento' => '2026-01-20']);
        $this->crearDocumento($tercero, $cuenta, 'ABON-1', ['estado' => EstadoDocumentoTercero::AbonadoParcial, 'saldo' => Dinero::desdePesos('40')]);
        $this->crearDocumento($tercero, $cuenta, 'ANT-1', ['naturaleza' => NaturalezaDocumento::AnticipoProveedor]);
        $this->crearDocumento($tercero, null, 'SIN-CUENTA-1');
        $this->crearDocumento($tercero, $cuenta, 'CANC-1', ['estado' => EstadoDocumentoTercero::Cancelado, 'saldo' => Dinero::cero()]);
        $this->crearDocumento($tercero, $cuenta, 'CXP-1', ['naturaleza' => NaturalezaDocumento::PorPagar]);
        $this->crearDocumento($tercero, $otraCuenta, 'OTRA-CUENTA-1');
        $this->crearDocumento($otroTercero, $cuenta, 'OTRO-TERCERO-1');

        $numeros = $this->getJson(self::BASE."/documentos-cruce?cuenta_id={$cuenta->id}&tercero_id={$tercero->id}")
            ->assertOk()
            ->json('data.*.numero_documento');

        $this->assertEqualsCanonicalizing(['PEND-1', 'VENC-1', 'ABON-1', 'ANT-1', 'SIN-CUENTA-1'], $numeros);
    }

    public function test_una_cuenta_sin_documento_cruce_no_ofrece_saldos(): void
    {
        $this->creador($this->crearEmpresaDePrueba());
        $cuentaBanco = $this->crearCuenta(['codigo' => '11100599', 'exige_tercero' => false, 'exige_documento_cruce' => false]);
        $tercero = $this->crearTercero();
        $this->crearDocumento($tercero, $cuentaBanco, 'PEND-1');

        $this->getJson(self::BASE."/documentos-cruce?cuenta_id={$cuentaBanco->id}&tercero_id={$tercero->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_no_acepta_cuenta_ni_tercero_de_otra_empresa(): void
    {
        $this->creador($this->crearEmpresaDePrueba());
        $cuentaAjena = $this->cuentaAnticipos();
        $terceroAjeno = $this->crearTercero();
        $this->crearDocumento($terceroAjeno, $cuentaAjena, 'AJENO-1');

        $this->creador($this->crearEmpresaDePrueba());

        $this->getJson(self::BASE."/documentos-cruce?cuenta_id={$cuentaAjena->id}&tercero_id={$terceroAjeno->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cuenta_id', 'tercero_id']);
    }

    public function test_un_comprobante_manual_cruza_un_anticipo_de_proveedor_por_su_numero(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->creador($empresa);
        $cuenta = $this->cuentaAnticipos();
        $tercero = $this->crearTercero();
        $anticipo = $this->crearDocumento($tercero, $cuenta, 'ANT-7', ['naturaleza' => NaturalezaDocumento::AnticipoProveedor]);

        $comprobanteId = $this->postJson(self::BASE, [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-02-01',
            'concepto' => 'Legalización del anticipo',
            'lineas' => [
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => 60, 'credito' => 0],
                [
                    'cuenta_id' => $cuenta->id, 'debito' => 0, 'credito' => 60,
                    'tercero_id' => $tercero->id, 'documento_cruce' => 'ANT-7',
                ],
            ],
        ])->assertStatus(201)->json('comprobante.id');

        $this->actingAsRolConPermisos('revisor', ['contabilidad.revisar', 'contabilidad.contabilizar'], $empresa);
        $this->postJson(self::BASE."/{$comprobanteId}/revisar")->assertOk();
        $this->postJson(self::BASE."/{$comprobanteId}/contabilizar")->assertOk();

        $this->assertDatabaseHas('documentos_tercero', [
            'id' => $anticipo->id,
            'naturaleza' => 'anticipo_proveedor',
            'saldo' => 4000,
            'estado' => 'abonado_parcial',
        ]);
    }
}
