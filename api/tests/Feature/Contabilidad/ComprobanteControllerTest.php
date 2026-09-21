<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Services\ComprobanteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class ComprobanteControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private function payloadValido(): array
    {
        $tipo = $this->crearTipoComprobante();
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();

        return [
            'tipo_comprobante_id' => $tipo->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Comprobante manual de prueba',
            'lineas' => [
                ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100],
            ],
        ];
    }

    public function test_crea_y_contabiliza_un_comprobante_manual(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/contabilidad/comprobantes', $this->payloadValido());

        $respuesta->assertStatus(201);
        $respuesta->assertJsonPath('comprobante.estado', 'contabilizado');
        $this->assertDatabaseCount('asientos_contables', 2);
    }

    public function test_rechaza_un_comprobante_descuadrado(): void
    {
        $this->actingAsAdmin();
        $payload = $this->payloadValido();
        $payload['lineas'][1]['credito'] = 50;

        $respuesta = $this->postJson('/api/v1/contabilidad/comprobantes', $payload);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('lineas');
    }

    public function test_rechaza_sin_lineas(): void
    {
        $this->actingAsAdmin();
        $payload = $this->payloadValido();
        unset($payload['lineas']);

        $respuesta = $this->postJson('/api/v1/contabilidad/comprobantes', $payload);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('lineas');
    }

    public function test_puede_ver_y_reversar_un_comprobante_contabilizado(): void
    {
        $this->actingAsAdmin();
        $creado = $this->postJson('/api/v1/contabilidad/comprobantes', $this->payloadValido());
        $comprobanteId = $creado->json('comprobante.id');

        $respuestaVer = $this->getJson("/api/v1/contabilidad/comprobantes/{$comprobanteId}");
        $respuestaVer->assertStatus(200)->assertJsonPath('comprobante.id', $comprobanteId);

        $respuestaReverso = $this->postJson(
            "/api/v1/contabilidad/comprobantes/{$comprobanteId}/reversar",
            ['motivo' => 'Error de digitación'],
        );

        $respuestaReverso->assertStatus(201);
        $this->assertDatabaseHas('comprobantes', ['id' => $comprobanteId, 'estado' => 'anulado']);
    }

    public function test_sin_autenticar_no_puede_crear(): void
    {
        $respuesta = $this->postJson('/api/v1/contabilidad/comprobantes', []);

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_reversar(): void
    {
        $this->actingAsAsesor();
        $payload = $this->payloadValido();
        $comprobante = app(ComprobanteService::class)->crearComprobante(
            $payload['tipo_comprobante_id'],
            $payload['fecha'],
            $payload['lineas'],
            $payload['concepto'],
        );

        $respuesta = $this->postJson("/api/v1/contabilidad/comprobantes/{$comprobante->id}/reversar", ['motivo' => 'x']);

        $respuesta->assertStatus(403);
        $this->assertDatabaseHas('comprobantes', ['id' => $comprobante->id, 'estado' => 'contabilizado']);
    }
}
