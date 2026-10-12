<?php

namespace Tests\Feature\Permisos;

use App\Usuarios\Constants\PermisoConstants;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\TestCase;

/**
 * El panel de roles y usuarios muestra nombres legibles: todo permiso del catálogo tiene etiqueta, todo módulo tiene
 * nombre y un único grupo del menú, y no quedan etiquetas de permisos que ya no existen.
 */
class EtiquetasPermisosTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use RefreshDatabase;

    /** @return list<string> claves "modulo.accion" del catálogo vigente */
    private function claves(): array
    {
        return collect(PermisoSeeder::CATALOGO)
            ->flatMap(fn (array $acciones, string $modulo) => collect($acciones)->map(fn (string $accion) => "{$modulo}.{$accion}"))
            ->values()
            ->all();
    }

    public function test_cada_permiso_del_catalogo_tiene_etiqueta_y_no_sobran_etiquetas(): void
    {
        $etiquetadas = array_keys(PermisoConstants::ACCIONES);

        $this->assertSame([], array_values(array_diff($this->claves(), $etiquetadas)), 'Permisos del catálogo sin etiqueta en PermisoConstants::ACCIONES.');
        $this->assertSame([], array_values(array_diff($etiquetadas, $this->claves())), 'Etiquetas de permisos que no están en el catálogo.');

        foreach (PermisoConstants::ACCIONES as $clave => $etiqueta) {
            $this->assertNotSame('', trim($etiqueta), "{$clave} tiene una etiqueta vacía.");
        }
    }

    public function test_cada_modulo_tiene_nombre_y_un_solo_grupo_del_menu(): void
    {
        $modulos = array_keys(PermisoSeeder::CATALOGO);
        $agrupados = collect(PermisoConstants::GRUPOS)->flatMap(fn (array $grupo) => $grupo['modulos'])->all();

        $this->assertEqualsCanonicalizing($modulos, array_keys(PermisoConstants::MODULOS));
        $this->assertEqualsCanonicalizing($modulos, $agrupados);
        $this->assertSame(count($agrupados), count(array_unique($agrupados)), 'Un módulo no puede estar en dos grupos.');
    }

    public function test_el_catalogo_del_panel_trae_las_etiquetas_en_el_orden_del_menu(): void
    {
        $this->seed(PermisoSeeder::class);
        $this->actingAsAdmin();

        $permisos = $this->getJson('/api/v1/permisos')->assertOk()->json('permisos');

        $this->assertCount(count($this->claves()), $permisos);
        $this->assertSame('auditoria', $permisos[0]['grupo']);
        $this->assertSame('administracion', $permisos[count($permisos) - 1]['grupo']);

        foreach ($permisos as $permiso) {
            $this->assertSame(PermisoConstants::ACCIONES["{$permiso['modulo']}.{$permiso['accion']}"], $permiso['accion_etiqueta']);
            $this->assertSame(PermisoConstants::MODULOS[$permiso['modulo']], $permiso['modulo_etiqueta']);
            $this->assertSame(PermisoConstants::GRUPOS[$permiso['grupo']]['etiqueta'], $permiso['grupo_etiqueta']);
        }

        $grupos = collect($permisos)->pluck('grupo')->unique()->values()->all();
        $this->assertSame(array_keys(PermisoConstants::GRUPOS), $grupos, 'Los grupos llegan en el orden del menú y cada uno de una sola vez.');
    }

    public function test_el_catalogo_exige_sesion_y_permiso_de_usuarios(): void
    {
        $this->getJson('/api/v1/permisos')->assertUnauthorized();

        $this->actingAsRolConPermisos('consulta', ['clientes.listar']);
        $this->getJson('/api/v1/permisos')->assertForbidden();

        $this->actingAsRolConPermisos('gestor_usuarios', ['usuarios.listar']);
        $this->getJson('/api/v1/permisos')->assertOk();
    }
}
