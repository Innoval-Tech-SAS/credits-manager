<?php

namespace Tests\Feature\Usuarios;

use App\Usuarios\Models\Permiso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\TestCase;

/**
 * La migración de permisos de revisión no debe quitar ni ampliar el acceso de nadie: quien creaba asientos manuales
 * sigue pudiendo contabilizarlos, quien exportaba informes puede imprimirlos, y una excepción por usuario se respeta.
 */
class PermisosRevisionMigracionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use RefreshDatabase;

    private const MIGRACION = 'database/migrations/2026_10_07_100200_agregar_permisos_revision_y_pdf_contables.php';

    private function migracion(): object
    {
        return require base_path(self::MIGRACION);
    }

    private function permisoId(string $modulo, string $accion): int
    {
        return Permiso::query()->firstOrCreate(['modulo' => $modulo, 'accion' => $accion])->id;
    }

    public function test_quien_creaba_asientos_manuales_recibe_revisar_y_contabilizar(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $contable = $this->actingAsRolConPermisos('contable_previo', ['contabilidad.crear_manual', 'informes_contables.exportar'], $empresa);

        $this->migracion()->up();

        $this->assertDatabaseHas('rol_permiso', ['rol_id' => $contable->rol_id, 'permiso_id' => $this->permisoId('contabilidad', 'revisar')]);
        $this->assertDatabaseHas('rol_permiso', ['rol_id' => $contable->rol_id, 'permiso_id' => $this->permisoId('contabilidad', 'contabilizar')]);
        $this->assertDatabaseHas('rol_permiso', ['rol_id' => $contable->rol_id, 'permiso_id' => $this->permisoId('informes_contables', 'imprimir')]);
    }

    public function test_quien_no_creaba_asientos_no_gana_revision_ni_contabilizacion(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $asesor = $this->actingAsRolConPermisos('solo_consulta_previo', ['contabilidad.listar'], $empresa);

        $this->migracion()->up();

        $this->assertDatabaseMissing('rol_permiso', ['rol_id' => $asesor->rol_id, 'permiso_id' => $this->permisoId('contabilidad', 'revisar')]);
        $this->assertDatabaseMissing('rol_permiso', ['rol_id' => $asesor->rol_id, 'permiso_id' => $this->permisoId('contabilidad', 'contabilizar')]);
    }

    public function test_una_excepcion_por_usuario_denegada_se_conserva(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $usuario = $this->actingAsRolConPermisos('contable_con_excepcion', ['contabilidad.crear_manual'], $empresa);
        DB::table('usuario_permiso')->insert([
            'usuario_id' => $usuario->id,
            'permiso_id' => $this->permisoId('contabilidad', 'crear_manual'),
            'concedido' => false,
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);

        $this->migracion()->up();

        $this->assertDatabaseHas('usuario_permiso', [
            'usuario_id' => $usuario->id,
            'permiso_id' => $this->permisoId('contabilidad', 'revisar'),
            'concedido' => false,
        ]);
    }

    public function test_el_rol_admin_recibe_los_tres_permisos_nuevos(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $admin = $this->actingAsAdmin($empresa);

        $this->migracion()->up();

        foreach ([['contabilidad', 'revisar'], ['contabilidad', 'contabilizar'], ['informes_contables', 'imprimir']] as [$modulo, $accion]) {
            $this->assertDatabaseHas('rol_permiso', ['rol_id' => $admin->rol_id, 'permiso_id' => $this->permisoId($modulo, $accion)]);
        }
    }

    public function test_deshacer_la_migracion_quita_solo_los_permisos_nuevos(): void
    {
        $this->permisoId('contabilidad', 'crear_manual');
        $this->migracion()->up();
        $this->migracion()->down();

        $this->assertDatabaseMissing('permisos', ['modulo' => 'contabilidad', 'accion' => 'revisar']);
        $this->assertDatabaseMissing('permisos', ['modulo' => 'contabilidad', 'accion' => 'contabilizar']);
        $this->assertDatabaseMissing('permisos', ['modulo' => 'informes_contables', 'accion' => 'imprimir']);
        $this->assertDatabaseHas('permisos', ['modulo' => 'contabilidad', 'accion' => 'crear_manual']);
    }
}
