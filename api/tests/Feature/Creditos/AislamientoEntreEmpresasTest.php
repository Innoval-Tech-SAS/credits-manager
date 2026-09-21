<?php

namespace Tests\Feature\Creditos;

use App\Creditos\Enums\EstadoCredito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class AislamientoEntreEmpresasTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_un_admin_no_puede_editar_las_condiciones_de_un_credito_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $creditoAjeno = $this->crearCredito($this->crearCliente(), ['estado' => EstadoCredito::Solicitado]);

        $this->actingAsAdmin();

        $this->patchJson("/api/v1/creditos/{$creditoAjeno->id}", [
            'valor_prestamo' => 5000,
            'tasa_interes' => 9,
            'plazo' => 12,
            'tipo_pago' => 'mensual',
            'fecha_desembolso' => '2026-03-01',
            'motivo' => 'Intento sobre un crédito ajeno',
        ])->assertStatus(404);

        $this->assertDatabaseHas('creditos', ['id' => $creditoAjeno->id, 'plazo' => 3]);
    }
}
