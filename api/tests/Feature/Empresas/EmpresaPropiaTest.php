<?php

namespace Tests\Feature\Empresas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class EmpresaPropiaTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_el_superadmin_recibe_null_porque_no_tiene_empresa_propia(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/v1/empresa')
            ->assertOk()
            ->assertExactJson(['empresa' => null]);
    }

    public function test_el_administrador_recibe_su_empresa(): void
    {
        $empresa = $this->crearEmpresaDePrueba(['nombre_empresa' => 'Empresa Propia Única']);
        $this->actingAsAdmin($empresa);

        $this->getJson('/api/v1/empresa')
            ->assertOk()
            ->assertJsonPath('empresa.id', $empresa->id)
            ->assertJsonPath('empresa.nombre_empresa', 'Empresa Propia Única');
    }

    public function test_sin_sesion_no_hay_respuesta(): void
    {
        $this->getJson('/api/v1/empresa')->assertUnauthorized();
    }
}
