<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Services\DocumentoTerceroService;
use App\Core\Dinero\Dinero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class DocumentoTerceroControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    public function test_lista_solo_los_documentos_pendientes_del_tercero_y_naturaleza_pedidos(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $otroTercero = $this->crearTercero();
        $servicio = app(DocumentoTerceroService::class);

        $pendiente = $servicio->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura_compra', 'FC-001', '2026-01-15', 'Compra', Dinero::desdePesos(1000), 'facturas', 1);
        $servicio->crearDocumento($tercero, NaturalezaDocumento::PorCobrar, 'factura_venta', 'FV-001', '2026-01-15', 'Venta', Dinero::desdePesos(500), 'facturas', 2);
        $servicio->crearDocumento($otroTercero, NaturalezaDocumento::PorPagar, 'factura_compra', 'FC-002', '2026-01-15', 'Compra otro tercero', Dinero::desdePesos(700), 'facturas', 3);
        $cancelado = $servicio->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura_compra', 'FC-003', '2026-01-15', 'Compra pagada', Dinero::desdePesos(200), 'facturas', 4);
        $servicio->aplicarValor($tercero, NaturalezaDocumento::PorPagar, [['documento_tercero_id' => $cancelado->id, 'valor' => 200]], 'egresos', 1);

        $respuesta = $this->getJson("/api/v1/terceros/{$tercero->id}/documentos-pendientes?naturaleza=por_pagar");

        $respuesta->assertStatus(200)->assertJsonCount(1, 'documentos');
        $respuesta->assertJsonFragment(['id' => $pendiente->id]);
    }

    public function test_naturaleza_invalida_devuelve_422(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();

        $respuesta = $this->getJson("/api/v1/terceros/{$tercero->id}/documentos-pendientes?naturaleza=invalida");

        $respuesta->assertStatus(422);
    }

    public function test_sin_autenticar_no_puede_consultar(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $this->salirDeSesion();

        $respuesta = $this->getJson("/api/v1/terceros/{$tercero->id}/documentos-pendientes?naturaleza=por_pagar");

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_consultar(): void
    {
        $usuario = $this->actingAsAsesor();
        $tercero = $this->crearTercero(['empresa_id' => $usuario->empresa_id]);

        $respuesta = $this->getJson("/api/v1/terceros/{$tercero->id}/documentos-pendientes?naturaleza=por_pagar");

        $respuesta->assertStatus(403);
    }

    public function test_tercero_inexistente_devuelve_404(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->getJson('/api/v1/terceros/999999/documentos-pendientes?naturaleza=por_pagar');

        $respuesta->assertStatus(404);
    }
}
