<?php

namespace Tests\Feature\Permisos;

use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Rol;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/** Sembrar deja activos exactamente los permisos vigentes del catálogo, sin borrar filas ni asignaciones. */
class PermisoSeederTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    /** @return list<string> */
    private function catalogo(): array
    {
        return collect(PermisoSeeder::CATALOGO)
            ->flatMap(fn (array $acciones, string $modulo) => collect($acciones)->map(fn (string $accion) => "{$modulo}.{$accion}"))
            ->sort()->values()->all();
    }

    /** @return list<string> */
    private function activos(): array
    {
        return Permiso::query()->get()->map(fn (Permiso $permiso) => "{$permiso->modulo}.{$permiso->accion}")->sort()->values()->all();
    }

    public function test_tras_sembrar_los_activos_son_exactamente_el_catalogo(): void
    {
        Permiso::query()->create(['modulo' => 'modulo_viejo', 'accion' => 'listar']);

        $this->seed(PermisoSeeder::class);

        $this->assertSame($this->catalogo(), $this->activos());
        $this->assertSoftDeleted('permisos', ['modulo' => 'modulo_viejo', 'accion' => 'listar']);
    }

    public function test_restaura_un_permiso_vigente_retirado_sin_duplicarlo(): void
    {
        $this->seed(PermisoSeeder::class);
        Permiso::query()->where(['modulo' => 'clientes', 'accion' => 'listar'])->firstOrFail()->delete();

        $this->seed(PermisoSeeder::class);

        $this->assertContains('clientes.listar', $this->activos());
        $this->assertSame(1, DB::table('permisos')->where(['modulo' => 'clientes', 'accion' => 'listar'])->count());
    }

    public function test_retirar_un_permiso_conserva_sus_asignaciones(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $viejo = Permiso::query()->create(['modulo' => 'modulo_viejo', 'accion' => 'listar']);
        $rol = Rol::create(['empresa_id' => $empresa->id, 'nombre' => 'contable']);
        $rol->permisos()->attach($viejo->id);

        $this->seed(PermisoSeeder::class);

        $this->assertDatabaseHas('rol_permiso', ['rol_id' => $rol->id, 'permiso_id' => $viejo->id]);
        $this->assertCount(0, $rol->permisos()->get(), 'El rol ya no ve el permiso retirado.');
    }

    public function test_sembrar_dos_veces_no_cambia_nada(): void
    {
        $this->seed(PermisoSeeder::class);
        $antes = DB::table('permisos')->orderBy('id')->get(['id', 'modulo', 'accion', 'borrado_en'])->toArray();

        $this->seed(PermisoSeeder::class);

        $this->assertEquals($antes, DB::table('permisos')->orderBy('id')->get(['id', 'modulo', 'accion', 'borrado_en'])->toArray());
    }
}
