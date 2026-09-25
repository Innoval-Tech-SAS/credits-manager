<?php

namespace Tests\Feature\Clientes;

use App\Terceros\Enums\TipoDocumentoTercero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class HistorialEmpleadorTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private function payloadValido(?int $empleadorId = null): array
    {
        return [
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'tipo_documento' => TipoDocumentoTercero::Cc->value,
            'numero_documento' => '1000000001',
            'direccion' => 'Calle 10 # 20-30',
            'ciudad' => 'Bogotá',
            'celular' => '3001234567',
            'correo' => 'juan@example.com',
            'empleador_id' => $empleadorId ?? $this->crearEmpleador()->id,
        ];
    }

    public function test_crear_cliente_abre_el_primer_periodo_vigente(): void
    {
        $this->actingAsAdmin();
        $empleador = $this->crearEmpleador();

        $respuesta = $this->postJson('/api/v1/clientes', $this->payloadValido($empleador->id));

        $respuesta->assertStatus(201);
        $clienteId = $respuesta->json('cliente.id');

        $this->assertDatabaseCount('cliente_empleador_historial', 1);
        $this->assertDatabaseHas('cliente_empleador_historial', [
            'cliente_id' => $clienteId,
            'empleador_id' => $empleador->id,
            'fecha_fin' => null,
        ]);
    }

    public function test_cambiar_empleador_cierra_el_periodo_anterior_y_abre_uno_nuevo(): void
    {
        $this->actingAsAdmin();
        $empleadorViejo = $this->crearEmpleador(['nombre' => 'Innoval Tech']);
        $empleadorNuevo = $this->crearEmpleador(['nombre' => 'Otra Empresa']);

        $creado = $this->postJson('/api/v1/clientes', $this->payloadValido($empleadorViejo->id));
        $clienteId = $creado->json('cliente.id');

        $respuesta = $this->patchJson("/api/v1/clientes/{$clienteId}", $this->payloadValido($empleadorNuevo->id));

        $respuesta->assertStatus(200);
        $this->assertDatabaseCount('cliente_empleador_historial', 2);
        $this->assertDatabaseHas('cliente_empleador_historial', [
            'cliente_id' => $clienteId,
            'empleador_id' => $empleadorViejo->id,
            'fecha_fin' => now()->toDateString(),
        ]);
        $this->assertDatabaseHas('cliente_empleador_historial', [
            'cliente_id' => $clienteId,
            'empleador_id' => $empleadorNuevo->id,
            'fecha_fin' => null,
        ]);
    }

    public function test_editar_sin_cambiar_empleador_no_crea_un_periodo_nuevo(): void
    {
        $this->actingAsAdmin();
        $empleador = $this->crearEmpleador();

        $creado = $this->postJson('/api/v1/clientes', $this->payloadValido($empleador->id));
        $clienteId = $creado->json('cliente.id');

        $respuesta = $this->patchJson("/api/v1/clientes/{$clienteId}", [
            ...$this->payloadValido($empleador->id),
            'nombres' => 'Juan',
            'apellidos' => 'Pérez Editado',
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseCount('cliente_empleador_historial', 1);
    }

    public function test_resumen_incluye_el_historial_con_bandera_vigente(): void
    {
        $this->actingAsAdmin();
        $empleadorViejo = $this->crearEmpleador();
        $empleadorNuevo = $this->crearEmpleador();

        $creado = $this->postJson('/api/v1/clientes', $this->payloadValido($empleadorViejo->id));
        $clienteId = $creado->json('cliente.id');
        $this->patchJson("/api/v1/clientes/{$clienteId}", $this->payloadValido($empleadorNuevo->id));

        $respuesta = $this->getJson("/api/v1/clientes/{$clienteId}/resumen");

        $respuesta->assertStatus(200);
        $respuesta->assertJsonCount(2, 'historial_empleadores');

        $historial = collect($respuesta->json('historial_empleadores'));
        $vigente = $historial->firstWhere('vigente', true);
        $finalizado = $historial->firstWhere('vigente', false);

        $this->assertSame($empleadorNuevo->id, $vigente['empleador_id']);
        $this->assertSame($empleadorViejo->id, $finalizado['empleador_id']);
        $this->assertNull($vigente['fecha_fin']);
        $this->assertNotNull($finalizado['fecha_fin']);
    }
}
