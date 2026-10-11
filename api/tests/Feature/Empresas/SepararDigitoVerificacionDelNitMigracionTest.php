<?php

namespace Tests\Feature\Empresas;

use App\Empleadores\Models\Empleador;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

/** Reproduce NITs "legados" (con el guion y el dígito pegados, o ya limpios) y corre la migración de datos. */
class SepararDigitoVerificacionDelNitMigracionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private Migration $separar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->separar = require database_path('migrations/2026_10_11_110300_separar_digito_verificacion_del_nit.php');
    }

    public function test_separa_el_nit_con_guion_en_empresas_y_calcula_el_de_un_nit_ya_limpio(): void
    {
        $conGuion = $this->crearEmpresaDePrueba(['nit' => '800.197.268-4', 'digito_verificacion' => null]);
        $limpio = $this->crearEmpresaDePrueba(['nit' => '860002964', 'digito_verificacion' => null]);

        $this->separar->up();

        $this->assertDatabaseHas('empresas', ['id' => $conGuion->id, 'nit' => '800197268', 'digito_verificacion' => '4']);
        $this->assertDatabaseHas('empresas', ['id' => $limpio->id, 'nit' => '860002964', 'digito_verificacion' => '4']);
    }

    public function test_separa_el_nit_con_guion_en_empleadores_y_respeta_el_nit_vacio(): void
    {
        $admin = $this->actingAsAdmin();
        $conGuion = Empleador::create(['empresa_id' => $admin->empresa_id, 'nombre' => 'Con guion SAS', 'nit' => '899999068-1']);
        $sinNit = Empleador::create(['empresa_id' => $admin->empresa_id, 'nombre' => 'Sin NIT SAS', 'nit' => null]);

        $this->separar->up();

        $this->assertDatabaseHas('empleadores', ['id' => $conGuion->id, 'nit' => '899999068', 'digito_verificacion' => '1']);
        $this->assertDatabaseHas('empleadores', ['id' => $sinNit->id, 'nit' => null, 'digito_verificacion' => null]);
    }
}
