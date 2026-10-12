<?php

namespace Tests\Feature\Permisos;

use App\Empresas\Models\Empresa;
use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * `facturacion.emitir` lo recibe quien tenía `facturacion.crear` y `facturacion.enviar` quien tenía `facturacion.imprimir`,
 * por rol y por excepción, así que nadie gana ni pierde acceso al desplegar; el admin recibe ambos.
 */
class MigracionPermisosEmitirEnviarFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const MIGRACION = 'migrations/2026_10_12_150000_agregar_permisos_emitir_y_enviar_facturacion.php';

    private const TRADUCCIONES = ['facturacion.crear' => 'facturacion.emitir', 'facturacion.imprimir' => 'facturacion.enviar'];

    private Empresa $empresa;

    /** @var array<string, Rol> */
    private array $roles = [];

    /** Parte del estado previo: sin `emitir` ni `enviar`. */
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
            'contable' => ['facturacion.listar_facturas', 'facturacion.crear', 'facturacion.imprimir'],
            'cartera' => ['facturacion.listar_facturas', 'facturacion.imprimir'],
            'asesor' => ['facturacion.listar_facturas'],
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
            ->orderBy('permisos.modulo')->orderBy('permisos.accion')
            ->get(['permisos.modulo', 'permisos.accion'])
            ->map(fn ($permiso) => "{$permiso->modulo}.{$permiso->accion}")
            ->all();
    }

    public function test_emitir_y_enviar_dan_lo_mismo_que_crear_e_imprimir_incluidas_las_excepciones(): void
    {
        $this->prepararRoles();
        $contable = $this->crearUsuario('contable');
        $contableRevocado = $this->crearUsuario('contable');
        $contableRevocado->permisosExtra()->attach($this->permiso('facturacion.crear')->id, ['concedido' => false]);
        $asesorConExcepcion = $this->crearUsuario('asesor');
        $asesorConExcepcion->permisosExtra()->attach($this->permiso('facturacion.imprimir')->id, ['concedido' => true]);
        $asesor = $this->crearUsuario('asesor');

        $this->migracion()->up();

        foreach ([$contable, $contableRevocado, $asesorConExcepcion, $asesor] as $usuario) {
            $usuario = $usuario->fresh();

            foreach (self::TRADUCCIONES as $origen => $destino) {
                [$moduloOrigen, $accionOrigen] = explode('.', $origen);
                [$modulo, $accion] = explode('.', $destino);

                $this->assertSame($usuario->tienePermiso($moduloOrigen, $accionOrigen), $usuario->tienePermiso($modulo, $accion), "{$usuario->nombre}: {$destino}.");
            }
        }

        $this->assertFalse($contableRevocado->fresh()->tienePermiso('facturacion', 'emitir'), 'La revocación por usuario se traduce.');
        $this->assertTrue($asesorConExcepcion->fresh()->tienePermiso('facturacion', 'enviar'), 'La concesión por usuario se traduce.');
        $this->assertFalse($asesor->fresh()->tienePermiso('facturacion', 'emitir'));
    }

    public function test_el_admin_recibe_ambos_y_los_demas_roles_solo_lo_que_ya_podian_hacer(): void
    {
        $this->prepararRoles();

        $this->migracion()->up();

        $this->assertContains('facturacion.emitir', $this->permisosDelRol('admin'));
        $this->assertContains('facturacion.enviar', $this->permisosDelRol('admin'));
        $this->assertContains('facturacion.emitir', $this->permisosDelRol('contable'));
        $this->assertSame(['facturacion.enviar', 'facturacion.imprimir', 'facturacion.listar_facturas'], $this->permisosDelRol('cartera'));
        $this->assertSame(['facturacion.listar_facturas'], $this->permisosDelRol('asesor'));
    }

    public function test_aplicarla_dos_veces_no_duplica_ni_cambia_asignaciones(): void
    {
        $this->prepararRoles();

        $this->migracion()->up();
        $primera = $this->permisosDelRol('contable');
        $this->migracion()->up();

        $this->assertSame($primera, $this->permisosDelRol('contable'));
    }

    public function test_revertirla_deja_las_asignaciones_como_estaban(): void
    {
        $this->prepararRoles();
        $antes = $this->permisosDelRol('contable');

        $this->migracion()->up();
        $this->migracion()->down();

        $this->assertSame($antes, $this->permisosDelRol('contable'));
        $this->assertSame(0, Permiso::withTrashed()->where(['modulo' => 'facturacion', 'accion' => 'emitir'])->count());
    }
}
