<?php

namespace Tests\Feature\Permisos;

use App\Empresas\Models\Empresa;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

/**
 * Emitir una factura exige `facturacion.emitir` y reenviar o reintentar su correo `facturacion.enviar`: crear un
 * borrador o descargar el PDF ya no alcanzan. El historial de envíos se lee con el permiso de lectura del documento.
 */
class PermisosEmitirEnviarFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion/documentos';

    private Empresa $empresa;

    private function prepararEmpresa(): void
    {
        $admin = $this->actingAsAdmin();
        $this->empresa = Empresa::findOrFail($admin->empresa_id);
        $this->crearReglasFacturacion();
    }

    private function actuarCon(array $permisos): Usuario
    {
        return $this->actingAsRolConPermisos('rol_'.uniqid(), $permisos, $this->empresa);
    }

    /** Borrador de factura creado como admin. */
    private function borrador(): array
    {
        $credito = $this->crearCredito($this->crearCliente());

        return $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id,
            'credito_id' => $credito->id,
            'lineas' => [['concepto' => 'INTERES.AMORT.ADM', 'descripcion' => 'Interés administrativo', 'valor' => '1000', 'tipo_concepto' => 'otros_gastos']],
        ])->assertCreated()->json('documento');
    }

    public function test_crear_borradores_no_permite_emitirlos(): void
    {
        $this->prepararEmpresa();
        $borrador = $this->borrador();
        $this->actuarCon(['facturacion.listar_facturas', 'facturacion.crear', 'facturacion.imprimir']);

        $this->postJson(self::BASE."/{$borrador['id']}/emitir")->assertForbidden();
        $this->assertDatabaseHas('documentos_facturacion', ['id' => $borrador['id'], 'numero' => null]);
    }

    public function test_con_emitir_y_lectura_emite_la_factura(): void
    {
        $this->prepararEmpresa();
        $borrador = $this->borrador();
        $this->actuarCon(['facturacion.listar_facturas', 'facturacion.emitir']);

        $emitida = $this->postJson(self::BASE."/{$borrador['id']}/emitir")->assertOk()->json('documento');

        $this->assertNotNull($emitida['numero']);
    }

    public function test_emitir_sin_poder_ver_el_documento_responde_403(): void
    {
        $this->prepararEmpresa();
        $borrador = $this->borrador();
        $this->actuarCon(['facturacion.emitir']);

        $this->postJson(self::BASE."/{$borrador['id']}/emitir")->assertForbidden();
    }

    public function test_emitir_no_permite_editar_ni_eliminar_borradores(): void
    {
        $this->prepararEmpresa();
        $borrador = $this->borrador();
        $this->actuarCon(['facturacion.listar_facturas', 'facturacion.emitir']);

        $this->deleteJson(self::BASE."/{$borrador['id']}")->assertForbidden();
        $this->patchJson(self::BASE."/{$borrador['id']}", ['lineas' => []])->assertForbidden();
    }

    public function test_imprimir_no_permite_reenviar_ni_reintentar_y_enviar_si(): void
    {
        $this->prepararEmpresa();
        $factura = $this->postJson(self::BASE."/{$this->borrador()['id']}/emitir")->assertOk()->json('documento');

        $this->actuarCon(['facturacion.listar_facturas', 'facturacion.imprimir']);
        $this->postJson(self::BASE."/{$factura['id']}/reenviar")->assertForbidden();
        $this->postJson(self::BASE."/{$factura['id']}/envios/999999/reintentar")->assertForbidden();

        $this->actuarCon(['facturacion.listar_facturas', 'facturacion.enviar']);
        foreach ([self::BASE."/{$factura['id']}/reenviar", self::BASE."/{$factura['id']}/envios/999999/reintentar"] as $uri) {
            $estado = $this->postJson($uri)->status();
            $this->assertNotSame(403, $estado, "{$uri} debe aceptar a quien tiene facturacion.enviar.");
            $this->assertLessThan(500, $estado);
        }
    }

    public function test_enviar_sin_poder_ver_el_documento_responde_403(): void
    {
        $this->prepararEmpresa();
        $factura = $this->postJson(self::BASE."/{$this->borrador()['id']}/emitir")->assertOk()->json('documento');
        $this->actuarCon(['facturacion.enviar']);

        $this->postJson(self::BASE."/{$factura['id']}/reenviar")->assertForbidden();
    }

    public function test_el_historial_de_envios_se_lee_con_la_lectura_del_documento(): void
    {
        $this->prepararEmpresa();
        $factura = $this->postJson(self::BASE."/{$this->borrador()['id']}/emitir")->assertOk()->json('documento');

        $this->actuarCon(['facturacion.listar_facturas']);
        $this->getJson(self::BASE."/{$factura['id']}/envios")->assertOk();

        $this->actuarCon(['facturacion.imprimir', 'facturacion.enviar']);
        $this->getJson(self::BASE."/{$factura['id']}/envios")->assertForbidden();
    }
}
