<?php

namespace Tests\Feature\Terceros;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/** Reproduce el estado previo (tipo_persona opcional), inserta terceros "legados" y corre la migración de datos. */
class RellenarTipoPersonaMigracionTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private Migration $exigirTipoPersona;

    private Migration $rellenar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exigirTipoPersona = require database_path('migrations/2026_09_24_010200_exigir_tipo_persona_en_terceros_table.php');
        $this->rellenar = require database_path('migrations/2026_09_24_010100_rellenar_tipo_persona_de_terceros.php');
    }

    private function insertarLegado(int $empresaId, string $tipoDocumento, string $numero, string $nombre, ?string $tipoPersona = null, ?string $borradoEn = null): int
    {
        return DB::table('terceros')->insertGetId([
            'empresa_id' => $empresaId,
            'tipo_documento' => $tipoDocumento,
            'numero_documento' => $numero,
            'nombre' => $nombre,
            'tipo_persona' => $tipoPersona,
            'activo' => true,
            'borrado_en' => $borradoEn,
        ]);
    }

    public function test_el_documento_define_el_tipo_de_persona_y_se_rellenan_sus_datos(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        Schema::disableForeignKeyConstraints();
        $this->exigirTipoPersona->down();

        $nit = $this->insertarLegado($empresa->id, 'nit', '900123456', 'Comercial Andina SAS');
        $cuatroPalabras = $this->insertarLegado($empresa->id, 'cc', '1000000001', 'Ana María Torres Rojas');
        $dosPalabras = $this->insertarLegado($empresa->id, 'ce', '1000000002', 'Juan Pérez');
        $unaPalabra = $this->insertarLegado($empresa->id, 'pasaporte', 'P123', 'Madonna');
        $contradictorio = $this->insertarLegado($empresa->id, 'cc', '1000000003', 'Luis Gómez', 'juridica');
        $borrado = $this->insertarLegado($empresa->id, 'nit', '860002964', 'Empresa Borrada SAS', null, '2026-01-01 00:00:00');
        $conGuion = $this->insertarLegado($empresa->id, 'nit', '800.197.268-4', 'Empresa Con Guion SAS');
        $ocupado = $this->insertarLegado($empresa->id, 'nit', '899999068', 'Empresa Ya Registrada SAS');
        $conGuionOcupado = $this->insertarLegado($empresa->id, 'nit', '899999068-1', 'Empresa Duplicada SAS');

        $this->rellenar->up();

        $this->assertDatabaseHas('terceros', [
            'id' => $nit, 'tipo_persona' => 'juridica', 'razon_social' => 'Comercial Andina SAS',
            'digito_verificacion' => '8', 'nombres' => null, 'apellidos' => null, 'nombre' => 'Comercial Andina SAS',
        ]);
        $this->assertDatabaseHas('terceros', [
            'id' => $cuatroPalabras, 'tipo_persona' => 'natural', 'nombres' => 'Ana María', 'apellidos' => 'Torres Rojas',
            'razon_social' => null, 'nombre' => 'Ana María Torres Rojas',
        ]);
        $this->assertDatabaseHas('terceros', ['id' => $dosPalabras, 'tipo_persona' => 'natural', 'nombres' => 'Juan', 'apellidos' => 'Pérez']);
        $this->assertDatabaseHas('terceros', ['id' => $unaPalabra, 'tipo_persona' => 'natural', 'nombres' => 'Madonna', 'apellidos' => null]);
        $this->assertDatabaseHas('terceros', ['id' => $contradictorio, 'tipo_persona' => 'natural', 'nombres' => 'Luis', 'apellidos' => 'Gómez']);
        $this->assertDatabaseHas('terceros', ['id' => $borrado, 'tipo_persona' => 'juridica', 'digito_verificacion' => '4']);
        $this->assertDatabaseHas('terceros', ['id' => $conGuion, 'numero_documento' => '800197268', 'digito_verificacion' => '4']);
        $this->assertDatabaseHas('terceros', ['id' => $ocupado, 'numero_documento' => '899999068', 'digito_verificacion' => '1']);
        $this->assertDatabaseHas('terceros', ['id' => $conGuionOcupado, 'numero_documento' => '899999068-1', 'digito_verificacion' => '1']);
    }

    public function test_tras_el_relleno_se_puede_exigir_el_tipo_de_persona(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        Schema::disableForeignKeyConstraints();
        $this->exigirTipoPersona->down();
        $this->insertarLegado($empresa->id, 'nit', '900123456', 'Comercial Andina SAS');
        $this->insertarLegado($empresa->id, 'cc', '1000000001', 'Ana Torres');

        $this->rellenar->up();
        $this->exigirTipoPersona->up();

        $this->assertSame(0, DB::table('terceros')->whereNull('tipo_persona')->count());
    }
}
