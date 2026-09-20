<?php

namespace Tests\Feature\Creditos;

use App\Contabilidad\Enums\EstadoComprobante;
use App\Contabilidad\Enums\EventoContable;
use App\Core\Dinero\Dinero;
use App\Creditos\Enums\EstadoCredito;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class CreditoTransicionesEstadoTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_aprueba_un_credito_solicitado(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['estado' => EstadoCredito::Solicitado]);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/aprobar");

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('creditos', ['id' => $credito->id, 'estado' => EstadoCredito::Aprobado->value]);
    }

    public function test_rechaza_aprobar_un_credito_que_no_esta_solicitado(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['estado' => EstadoCredito::Desembolsado]);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/aprobar");

        $respuesta->assertStatus(422)->assertJsonValidationErrors('estado');
    }

    public function test_desembolsa_un_credito_aprobado(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['estado' => EstadoCredito::Aprobado]);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/desembolsar");

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('creditos', ['id' => $credito->id, 'estado' => EstadoCredito::Desembolsado->value]);
    }

    public function test_cancela_un_credito_solicitado(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['estado' => EstadoCredito::Solicitado]);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/cancelar");

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('creditos', ['id' => $credito->id, 'estado' => EstadoCredito::Cancelado->value]);
    }

    public function test_rechaza_cancelar_un_credito_desembolsado(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['estado' => EstadoCredito::Desembolsado]);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/cancelar");

        $respuesta->assertStatus(422)->assertJsonValidationErrors('estado');
    }

    public function test_cancela_un_credito_desembolsado_dentro_de_24_horas(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, [
            'estado' => EstadoCredito::Desembolsado,
            'desembolsado_en' => now()->subHours(2),
        ]);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/cancelar");

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('creditos', ['id' => $credito->id, 'estado' => EstadoCredito::Cancelado->value]);
    }

    public function test_rechaza_cancelar_un_credito_con_pagos_registrados_aunque_este_dentro_de_24_horas(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, [
            'estado' => EstadoCredito::Desembolsado,
            'desembolsado_en' => now()->subHours(2),
        ]);
        $this->crearCuota($credito, ['numero_cuota' => 1]);

        app(PagoService::class)->registrarPago($credito->fresh(), Dinero::desdePesos(100), FormaPago::Efectivo, null, null);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/cancelar");

        $respuesta->assertStatus(422)->assertJsonValidationErrors('credito');
        $this->assertDatabaseHas('creditos', ['id' => $credito->id, 'estado' => EstadoCredito::Desembolsado->value]);
    }

    public function test_rechaza_cancelar_un_credito_desembolsado_hace_mas_de_24_horas(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, [
            'estado' => EstadoCredito::Desembolsado,
            'desembolsado_en' => now()->subHours(25),
        ]);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/cancelar");

        $respuesta->assertStatus(422)->assertJsonValidationErrors('estado');
        $this->assertDatabaseHas('creditos', ['id' => $credito->id, 'estado' => EstadoCredito::Desembolsado->value]);
    }

    public function test_cancelar_credito_desembolsado_reversa_su_comprobante(): void
    {
        $this->actingAsAdmin();
        $this->crearRegla(EventoContable::DesembolsoCredito);
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['estado' => EstadoCredito::Aprobado]);

        $this->postJson("/api/v1/creditos/{$credito->id}/desembolsar")->assertStatus(200);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/cancelar");

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('comprobantes', [
            'modulo_origen' => 'creditos',
            'origen_id' => $credito->id,
            'estado' => EstadoComprobante::Anulado->value,
        ]);
        $this->assertDatabaseHas('comprobantes', ['modulo_origen' => 'reverso', 'origen_id' => $this->buscarComprobanteId($credito->id)]);
    }

    public function test_credito_inexistente_devuelve_404_en_las_tres_transiciones(): void
    {
        $this->actingAsAdmin();

        foreach (['aprobar', 'desembolsar', 'cancelar'] as $accion) {
            $this->postJson("/api/v1/creditos/999999/{$accion}")->assertStatus(404);
        }
    }

    public function test_sin_autenticar_no_puede_aprobar(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['estado' => EstadoCredito::Solicitado]);
        $this->salirDeSesion();

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/aprobar");

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_aprobar_desembolsar_ni_cancelar(): void
    {
        $this->actingAsAsesor();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['estado' => EstadoCredito::Solicitado]);

        foreach (['aprobar', 'desembolsar', 'cancelar'] as $accion) {
            $this->postJson("/api/v1/creditos/{$credito->id}/{$accion}")->assertStatus(403);
        }

        $this->assertDatabaseHas('creditos', ['id' => $credito->id, 'estado' => EstadoCredito::Solicitado->value]);
    }

    private function buscarComprobanteId(int $creditoId): int
    {
        return DB::table('comprobantes')->where('modulo_origen', 'creditos')->where('origen_id', $creditoId)->value('id');
    }
}
