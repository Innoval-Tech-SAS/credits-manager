<?php

namespace Tests\Feature\Empleadores;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class ListarEmpleadoresTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_lista_empleadores_sin_filtro(): void
    {
        $this->actingAsAdmin();
        $this->crearEmpleador(['nombre' => 'Alfa']);
        $this->crearEmpleador(['nombre' => 'Beta']);

        $respuesta = $this->getJson('/api/v1/empleadores');

        $respuesta->assertStatus(200);
        $this->assertCount(2, $respuesta->json('data'));
    }

    public function test_filtra_por_busqueda(): void
    {
        $this->actingAsAdmin();
        $this->crearEmpleador(['nombre' => 'Almacenes Éxito']);
        $this->crearEmpleador(['nombre' => 'Bancolombia']);

        $respuesta = $this->getJson('/api/v1/empleadores?search=Exito');

        $respuesta->assertStatus(200);
        $this->assertCount(0, $respuesta->json('data'));

        $respuesta = $this->getJson('/api/v1/empleadores?search=Almacenes');

        $respuesta->assertStatus(200);
        $this->assertCount(1, $respuesta->json('data'));
    }

    public function test_sin_autenticar_no_puede_listar(): void
    {
        $respuesta = $this->getJson('/api/v1/empleadores');

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_listar(): void
    {
        $this->actingAsAsesor();

        $respuesta = $this->getJson('/api/v1/empleadores');

        $respuesta->assertStatus(403);
    }
}
