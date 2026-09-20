<?php

namespace Tests\Feature\Empleadores;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class CrearEmpleadorValidationTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_crea_empleador_solo_con_nombre(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/empleadores', ['nombre' => 'Almacenes Éxito']);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('empleadores', ['nombre' => 'Almacenes Éxito']);
    }

    public function test_crea_empleador_con_datos_completos(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/empleadores', [
            'nombre' => 'Bancolombia',
            'nit' => '890903938-8',
            'direccion' => 'Cra 48 # 26-85',
            'telefono' => '6045108000',
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('empleadores', ['nombre' => 'Bancolombia', 'nit' => '890903938-8']);
    }

    public function test_rechaza_nombre_faltante(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/empleadores', []);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    public function test_rechaza_nombre_duplicado(): void
    {
        $this->actingAsAdmin();
        $this->crearEmpleador(['nombre' => 'Ya existe']);

        $respuesta = $this->postJson('/api/v1/empleadores', ['nombre' => 'Ya existe']);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    public function test_sin_autenticar_no_puede_crear_empleadores(): void
    {
        $respuesta = $this->postJson('/api/v1/empleadores', ['nombre' => 'Cualquiera']);

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_crear_empleadores(): void
    {
        $this->actingAsAsesor();

        $respuesta = $this->postJson('/api/v1/empleadores', ['nombre' => 'Cualquiera']);

        $respuesta->assertStatus(403);
    }
}
