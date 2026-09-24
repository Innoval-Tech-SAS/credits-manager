<?php

namespace Tests\Feature\Geografia;

use App\Geografia\Models\Estado;
use App\Geografia\Models\Municipio;
use App\Geografia\Models\Pais;
use Database\Seeders\GeografiaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeografiaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_colombia_completa_segun_divipola(): void
    {
        $this->seed(GeografiaSeeder::class);

        $colombia = Pais::where('codigo', 'CO')->firstOrFail();

        $this->assertSame(33, Estado::where('pais_id', $colombia->id)->count());
        $this->assertSame(1122, Municipio::count());
        $this->assertSame(32, Municipio::where('es_capital', true)->count());
    }

    public function test_los_estados_traen_sus_capitales_y_bogota_es_un_estado_propio(): void
    {
        $this->seed(GeografiaSeeder::class);

        $magdalena = Estado::where('codigo', '47')->firstOrFail();
        $santaMarta = Municipio::where('estado_id', $magdalena->id)->where('codigo', '47001')->firstOrFail();

        $this->assertSame('Magdalena', $magdalena->nombre);
        $this->assertSame('Santa Marta', $santaMarta->nombre);
        $this->assertTrue($santaMarta->es_capital);

        $bogota = Estado::where('codigo', '11')->firstOrFail();
        $this->assertSame(1, Municipio::where('estado_id', $bogota->id)->count());

        $cundinamarca = Estado::where('codigo', '25')->firstOrFail();
        $this->assertSame(0, Municipio::where('estado_id', $cundinamarca->id)->where('es_capital', true)->count());
    }

    public function test_correrlo_de_nuevo_no_duplica_ni_reactiva_lo_que_el_superadmin_desactivo(): void
    {
        $this->seed(GeografiaSeeder::class);
        Municipio::where('codigo', '47001')->update(['activo' => false]);

        $this->seed(GeografiaSeeder::class);

        $this->assertSame(1122, Municipio::count());
        $this->assertSame(33, Estado::count());
        $this->assertFalse(Municipio::where('codigo', '47001')->firstOrFail()->activo);
    }
}
