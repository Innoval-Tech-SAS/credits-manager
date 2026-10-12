<?php

namespace Tests\Feature\Permisos;

use App\Empresas\Models\Empresa;
use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * El permiso por hoja no cambia el acceso de nadie al desplegar: `importar_<hoja>` lo recibe exactamente quien tenía
 * `importaciones.ejecutar` y el permiso de crear del módulo destino, contando las excepciones por usuario.
 */
class MigracionPermisosImportacionPorHojaTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const MIGRACION = 'migrations/2026_10_12_130000_separar_permisos_importacion_por_hoja.php';

    /** Muestra de la migración: hoja => permiso destino que se exigía junto con `ejecutar`. */
    private const MUESTRA = [
        'importar_clientes' => 'clientes.crear',
        'importar_plan_cuotas' => 'creditos.crear',
        'importar_pagos' => 'pagos.crear',
        'importar_saldos_iniciales' => 'configuracion_contable.crear_plan_cuentas',
        'importar_valores_uvt' => 'tributario.crear_valores_uvt',
    ];

    private Empresa $empresa;

    /** @var array<string, Rol> */
    private array $roles = [];

    /** Parte del estado previo: `listar` y `ejecutar` vigentes y sin permisos por hoja. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->migracion()->down();
    }

    private function migracion(): object
    {
        return require database_path(self::MIGRACION);
    }

    private function permiso(string $clave): Permiso
    {
        [$modulo, $accion] = explode('.', $clave);

        return Permiso::query()->firstOrCreate(['modulo' => $modulo, 'accion' => $accion]);
    }

    private function prepararRoles(): void
    {
        $this->empresa = $this->crearEmpresaDePrueba();

        $asignaciones = [
            'admin' => [],
            'cargador' => ['importaciones.listar', 'importaciones.ejecutar', 'clientes.crear', 'pagos.crear'],
            'consulta' => ['importaciones.listar', 'clientes.crear', 'creditos.crear'],
            'asesor' => ['clientes.crear'],
        ];

        foreach ($asignaciones as $nombre => $claves) {
            $this->roles[$nombre] = Rol::create(['empresa_id' => $this->empresa->id, 'nombre' => $nombre]);
            $this->roles[$nombre]->permisos()->sync(collect($claves)->map(fn (string $clave) => $this->permiso($clave)->id)->all());
        }
    }

    private function crearUsuario(string $rol): Usuario
    {
        return Usuario::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => ucfirst($rol),
            'usuario' => $rol.'_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $this->roles[$rol]->id,
            'activo' => true,
        ]);
    }

    /** @return list<string> */
    private function permisosDelRol(string $rol): array
    {
        return DB::table('rol_permiso')
            ->join('permisos', 'permisos.id', '=', 'rol_permiso.permiso_id')
            ->where('rol_permiso.rol_id', $this->roles[$rol]->id)
            ->whereNull('permisos.borrado_en')
            ->orderBy('permisos.modulo')->orderBy('permisos.accion')
            ->get(['permisos.modulo', 'permisos.accion'])
            ->map(fn ($permiso) => "{$permiso->modulo}.{$permiso->accion}")
            ->all();
    }

    public function test_cada_hoja_la_recibe_quien_tenia_ejecutar_y_el_permiso_destino_incluidas_las_excepciones(): void
    {
        $this->prepararRoles();
        $cargador = $this->crearUsuario('cargador');
        $cargadorRevocado = $this->crearUsuario('cargador');
        $cargadorRevocado->permisosExtra()->attach($this->permiso('pagos.crear')->id, ['concedido' => false]);
        $consultaConExcepcion = $this->crearUsuario('consulta');
        $consultaConExcepcion->permisosExtra()->attach($this->permiso('importaciones.ejecutar')->id, ['concedido' => true]);
        $asesor = $this->crearUsuario('asesor');
        $usuarios = [$cargador, $cargadorRevocado, $consultaConExcepcion, $asesor];

        $antes = [];
        foreach ($usuarios as $usuario) {
            foreach (self::MUESTRA as $hoja => $destino) {
                [$modulo, $accion] = explode('.', $destino);
                $antes[$usuario->id][$hoja] = $usuario->tienePermiso('importaciones', 'ejecutar') && $usuario->tienePermiso($modulo, $accion);
            }
        }

        $this->migracion()->up();

        foreach ($usuarios as $usuario) {
            $usuario = $usuario->fresh();

            foreach (self::MUESTRA as $hoja => $destino) {
                $this->assertSame($antes[$usuario->id][$hoja], $usuario->tienePermiso('importaciones', $hoja), "{$usuario->nombre}: {$hoja}.");
            }
        }

        $this->assertTrue($cargador->fresh()->tienePermiso('importaciones', 'importar_pagos'));
        $this->assertFalse($cargadorRevocado->fresh()->tienePermiso('importaciones', 'importar_pagos'), 'La revocación del destino se respeta.');
        $this->assertTrue($cargadorRevocado->fresh()->tienePermiso('importaciones', 'importar_clientes'));
        $this->assertTrue($consultaConExcepcion->fresh()->tienePermiso('importaciones', 'importar_plan_cuotas'), 'La concesión de ejecutar por usuario cuenta.');
        $this->assertFalse($asesor->fresh()->tienePermiso('importaciones', 'importar_clientes'), 'Sin ejecutar no recibe nada.');
    }

    public function test_el_admin_recibe_todas_las_hojas_y_los_demas_roles_solo_las_que_ya_podian_importar(): void
    {
        $this->prepararRoles();

        $this->migracion()->up();

        $this->assertCount(11, array_filter($this->permisosDelRol('admin'), fn (string $clave) => str_starts_with($clave, 'importaciones.importar_')));
        $this->assertSame(['clientes.crear', 'importaciones.importar_clientes', 'importaciones.importar_pagos', 'pagos.crear'], $this->permisosDelRol('cargador'));
        $this->assertSame(['clientes.crear', 'creditos.crear'], $this->permisosDelRol('consulta'), 'Listar sin ejecutar no da ninguna hoja.');
        $this->assertSame(['clientes.crear'], $this->permisosDelRol('asesor'));
    }

    public function test_listar_y_ejecutar_quedan_retirados_sin_borrar_sus_asignaciones(): void
    {
        $this->prepararRoles();
        $cargador = $this->crearUsuario('cargador');
        $asignaciones = DB::table('rol_permiso')->where('rol_id', $this->roles['cargador']->id)->count();

        $this->migracion()->up();

        $cargador = $cargador->fresh();
        $this->assertFalse($cargador->tienePermiso('importaciones', 'ejecutar'));
        $this->assertFalse($cargador->tienePermiso('importaciones', 'listar'));
        $this->assertDatabaseHas('permisos', ['modulo' => 'importaciones', 'accion' => 'ejecutar']);
        $this->assertSame($asignaciones + 2, DB::table('rol_permiso')->where('rol_id', $this->roles['cargador']->id)->count());
    }

    public function test_el_catalogo_vigente_tiene_una_hoja_por_tipo_y_no_los_genericos(): void
    {
        $this->assertSame([
            'importar_clientes', 'importar_creditos', 'importar_plan_cuotas', 'importar_pagos', 'importar_saldos_iniciales',
            'importar_plan_cuentas', 'importar_terceros', 'importar_gastos', 'importar_actividades_economicas',
            'importar_valores_uvt', 'importar_tarifas_reteica',
        ], PermisoSeeder::CATALOGO['importaciones']);
    }

    public function test_tras_migrar_los_permisos_vigentes_de_importaciones_son_los_del_catalogo(): void
    {
        $this->migracion()->up();

        $vigentes = Permiso::query()->where('modulo', 'importaciones')->orderBy('id')->pluck('accion')->all();

        $this->assertEqualsCanonicalizing(PermisoSeeder::CATALOGO['importaciones'], $vigentes);
    }

    public function test_aplicarla_dos_veces_no_duplica_ni_cambia_asignaciones(): void
    {
        $this->prepararRoles();
        $revocado = $this->crearUsuario('cargador');
        $revocado->permisosExtra()->attach($this->permiso('pagos.crear')->id, ['concedido' => false]);

        $this->migracion()->up();
        $primera = $this->permisosDelRol('cargador');
        $excepciones = DB::table('usuario_permiso')->count();
        $this->migracion()->up();

        $this->assertSame($primera, $this->permisosDelRol('cargador'));
        $this->assertSame($excepciones, DB::table('usuario_permiso')->count());
    }

    public function test_revertirla_deja_las_asignaciones_como_estaban(): void
    {
        $this->prepararRoles();
        $cargador = $this->crearUsuario('cargador');
        $antes = $this->permisosDelRol('cargador');

        $this->migracion()->up();
        $this->migracion()->down();

        $this->assertSame($antes, $this->permisosDelRol('cargador'));
        $this->assertTrue($cargador->fresh()->tienePermiso('importaciones', 'ejecutar'));
        $this->assertSame(0, Permiso::withTrashed()->where(['modulo' => 'importaciones', 'accion' => 'importar_clientes'])->count());
    }
}
