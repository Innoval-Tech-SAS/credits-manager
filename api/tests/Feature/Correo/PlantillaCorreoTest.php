<?php

namespace Tests\Feature\Correo;

use App\Correo\Models\PlantillaCorreo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\SimulaCorreo;
use Tests\TestCase;

class PlantillaCorreoTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;
    use SimulaCorreo;

    private function datosValidos(array $sobrescribir = []): array
    {
        return array_merge([
            'tipo' => 'cobranza',
            'nombre' => 'Recordatorio estándar',
            'asunto' => 'Cuota {cuota} - {empresa}',
            'cuerpo' => '<p>Hola {cliente}, su cuota {cuota} por {valor} vence el {fecha_vencimiento}.</p>',
        ], $sobrescribir);
    }

    public function test_la_primera_plantilla_del_tipo_queda_predeterminada_sola(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/correo/plantillas', $this->datosValidos());

        $respuesta->assertCreated()->assertJsonPath('plantilla.es_predeterminada', true);
    }

    public function test_la_segunda_plantilla_no_queda_predeterminada(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/v1/correo/plantillas', $this->datosValidos())->assertCreated();

        $respuesta = $this->postJson('/api/v1/correo/plantillas', $this->datosValidos(['nombre' => 'Otra plantilla']));

        $respuesta->assertCreated()->assertJsonPath('plantilla.es_predeterminada', false);
    }

    public function test_marcar_predeterminada_desmarca_la_anterior(): void
    {
        $this->actingAsAdmin();
        $primera = $this->postJson('/api/v1/correo/plantillas', $this->datosValidos())->json('plantilla');
        $segunda = $this->postJson('/api/v1/correo/plantillas', $this->datosValidos(['nombre' => 'Otra']))->json('plantilla');

        $this->postJson("/api/v1/correo/plantillas/{$segunda['id']}/predeterminada")
            ->assertOk()
            ->assertJsonPath('plantilla.es_predeterminada', true);

        $this->assertFalse(PlantillaCorreo::query()->find($primera['id'])->es_predeterminada);
        $this->assertTrue(PlantillaCorreo::query()->find($segunda['id'])->es_predeterminada);
    }

    public function test_no_se_puede_eliminar_la_predeterminada_sin_marcar_otra_antes(): void
    {
        $this->actingAsAdmin();
        $plantilla = $this->postJson('/api/v1/correo/plantillas', $this->datosValidos())->json('plantilla');

        $this->deleteJson("/api/v1/correo/plantillas/{$plantilla['id']}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plantilla']);

        $this->assertDatabaseHas('plantillas_correo', ['id' => $plantilla['id']]);
    }

    public function test_se_puede_eliminar_una_plantilla_que_no_es_la_predeterminada(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/v1/correo/plantillas', $this->datosValidos())->assertCreated();
        $segunda = $this->postJson('/api/v1/correo/plantillas', $this->datosValidos(['nombre' => 'Otra']))->json('plantilla');

        $this->deleteJson("/api/v1/correo/plantillas/{$segunda['id']}")->assertNoContent();

        $this->assertSoftDeleted('plantillas_correo', ['id' => $segunda['id']]);
    }

    public function test_cada_tipo_tiene_su_propia_predeterminada(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/v1/correo/plantillas', $this->datosValidos(['tipo' => 'cobranza']))->assertCreated();
        $this->postJson('/api/v1/correo/plantillas', $this->datosValidos([
            'tipo' => 'facturacion',
            'asunto' => '{tipo_documento} {numero_documento}',
            'cuerpo' => '<p>Adjunto {numero_documento} por {valor_total}</p>',
        ]))->assertCreated()->assertJsonPath('plantilla.es_predeterminada', true);

        $this->getJson('/api/v1/correo/plantillas?tipo=cobranza')->assertOk()->assertJsonCount(1, 'plantillas');
        $this->getJson('/api/v1/correo/plantillas?tipo=facturacion')->assertOk()->assertJsonCount(1, 'plantillas');
    }

    public function test_422_si_la_plantilla_usa_una_variable_que_no_existe_para_el_tipo(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/correo/plantillas', $this->datosValidos(['tipo' => 'facturacion', 'cuerpo' => '<p>{cuota}</p>']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuerpo']);
    }

    public function test_actualizar_conserva_el_tipo_y_valida_sus_variables(): void
    {
        $this->actingAsAdmin();
        $plantilla = $this->postJson('/api/v1/correo/plantillas', $this->datosValidos())->json('plantilla');

        $this->putJson("/api/v1/correo/plantillas/{$plantilla['id']}", [
            'nombre' => 'Renombrada',
            'asunto' => 'Cobro {cuota}',
            'cuerpo' => '<p>{cliente} debe {valor}</p>',
        ])->assertOk()->assertJsonPath('plantilla.nombre', 'Renombrada');

        $this->putJson("/api/v1/correo/plantillas/{$plantilla['id']}", [
            'nombre' => 'Renombrada',
            'asunto' => 'Cobro',
            'cuerpo' => '<p>{numero_documento}</p>',
        ])->assertUnprocessable()->assertJsonValidationErrors(['cuerpo']);
    }

    public function test_vista_previa_no_guarda_nada(): void
    {
        $this->simulaCorreo();
        $this->actingAsAdmin();

        $this->postJson('/api/v1/correo/plantillas/vista-previa', [
            'tipo' => 'cobranza',
            'asunto' => 'Cuota {cuota}',
            'cuerpo' => '<p>{cliente}: {valor}</p>',
        ])
            ->assertOk()
            ->assertJsonPath('vista_previa.asunto', 'Cuota 3')
            ->assertJsonPath('vista_previa.cuerpo', '<p>Juan Pérez: $ 350.000,00</p>');

        $this->assertDatabaseCount('plantillas_correo', 0);
    }

    public function test_401_sin_sesion(): void
    {
        $this->postJson('/api/v1/correo/plantillas', $this->datosValidos())->assertUnauthorized();
    }

    public function test_403_para_un_asesor(): void
    {
        $this->actingAsAsesor();

        $this->postJson('/api/v1/correo/plantillas', $this->datosValidos())->assertForbidden();
        $this->assertDatabaseCount('plantillas_correo', 0);
    }

    public function test_una_empresa_no_ve_ni_edita_las_plantillas_de_otra(): void
    {
        $this->actingAsAdmin();
        $ajena = $this->postJson('/api/v1/correo/plantillas', $this->datosValidos())->json('plantilla');

        $this->actingAsAdmin();

        $this->getJson('/api/v1/correo/plantillas?tipo=cobranza')->assertOk()->assertJsonCount(0, 'plantillas');
        $this->putJson("/api/v1/correo/plantillas/{$ajena['id']}", ['nombre' => 'x', 'asunto' => 'x', 'cuerpo' => '<p>x</p>'])->assertNotFound();
        $this->deleteJson("/api/v1/correo/plantillas/{$ajena['id']}")->assertNotFound();
    }
}
