<?php

namespace Tests\Feature\Auditoria;

use App\Auditoria\Enums\AccionAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\TestCase;

class BitacoraAuditoriaTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use RefreshDatabase;

    private const URL = '/api/v1/auditoria/bitacora';

    private function idsDe(array $respuesta): array
    {
        return collect($respuesta['data'])->pluck('evento_id')->sort()->values()->all();
    }

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
    }

    public function test_admin_ve_solo_los_eventos_de_su_empresa(): void
    {
        $admin = $this->actingAsAdmin();
        $otra = $this->crearEmpresaDePrueba();

        $propio = $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id]);
        $this->crearEventoBitacora(['empresa_id' => $otra->id]);
        $this->crearEventoBitacora(['empresa_id' => null, 'es_superadmin' => true]);

        $respuesta = $this->getJson(self::URL)->assertOk();

        $this->assertSame([$propio->evento_id], $this->idsDe($respuesta->json()));
    }

    public function test_admin_ve_la_bitacora_aunque_no_tenga_el_permiso_asignado(): void
    {
        $admin = $this->actingAsAdmin();
        $this->assertFalse($admin->rol->permisos()->where('modulo', 'auditoria')->exists());

        $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id]);

        $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_rol_auditoria_con_permiso_ve_solo_su_empresa(): void
    {
        $auditor = $this->actingAsRolConPermisos('auditoria', ['auditoria.listar']);
        $otra = $this->crearEmpresaDePrueba();

        $propio = $this->crearEventoBitacora(['empresa_id' => $auditor->empresa_id]);
        $this->crearEventoBitacora(['empresa_id' => $otra->id]);

        $respuesta = $this->getJson(self::URL)->assertOk();

        $this->assertSame([$propio->evento_id], $this->idsDe($respuesta->json()));
    }

    public function test_rol_auditoria_sin_el_permiso_recibe_403(): void
    {
        $this->actingAsRolConPermisos('auditoria', ['contabilidad.listar_comprobantes']);

        $this->getJson(self::URL)->assertStatus(403);
    }

    public function test_un_rol_operativo_sin_permiso_recibe_403(): void
    {
        $this->actingAsAsesor();

        $this->getJson(self::URL)->assertStatus(403);
    }

    public function test_superadmin_ve_todas_las_empresas_y_los_eventos_sin_empresa(): void
    {
        $this->actingAsSuperAdmin();
        $a = $this->crearEmpresaDePrueba();
        $b = $this->crearEmpresaDePrueba();

        $ids = [
            $this->crearEventoBitacora(['empresa_id' => $a->id])->evento_id,
            $this->crearEventoBitacora(['empresa_id' => $b->id])->evento_id,
            $this->crearEventoBitacora(['empresa_id' => null, 'es_superadmin' => true])->evento_id,
        ];
        sort($ids);

        $respuesta = $this->getJson(self::URL)->assertOk();

        $this->assertSame($ids, $this->idsDe($respuesta->json()));
    }

    public function test_superadmin_filtra_por_empresa(): void
    {
        $this->actingAsSuperAdmin();
        $a = $this->crearEmpresaDePrueba();
        $b = $this->crearEmpresaDePrueba();

        $deA = $this->crearEventoBitacora(['empresa_id' => $a->id]);
        $this->crearEventoBitacora(['empresa_id' => $b->id]);

        $respuesta = $this->getJson(self::URL.'?empresa_id='.$a->id)->assertOk();

        $this->assertSame([$deA->evento_id], $this->idsDe($respuesta->json()));
    }

    public function test_un_usuario_no_superadmin_que_envia_empresa_id_ajeno_no_ve_datos_ajenos(): void
    {
        $admin = $this->actingAsAdmin();
        $otra = $this->crearEmpresaDePrueba();

        $propio = $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id]);
        $this->crearEventoBitacora(['empresa_id' => $otra->id]);

        $respuesta = $this->getJson(self::URL.'?empresa_id='.$otra->id)->assertOk();

        $this->assertSame([$propio->evento_id], $this->idsDe($respuesta->json()));
    }

    public function test_filtra_por_modulo_accion_autor_entidad_y_rango_de_fechas(): void
    {
        $admin = $this->actingAsAdmin();
        $empresa = $admin->empresa_id;

        $objetivo = $this->crearEventoBitacora([
            'empresa_id' => $empresa, 'modulo' => 'creditos', 'accion' => AccionAuditoria::Aprobar,
            'autor_nombre' => 'Zulema Ortiz', 'autor_usuario' => 'zortiz', 'entidad' => 'credito', 'entidad_id' => 7,
            'fecha_hora' => '2026-03-15 10:00:00',
        ]);
        $this->crearEventoBitacora(['empresa_id' => $empresa, 'modulo' => 'clientes', 'fecha_hora' => '2026-03-15 10:00:00']);
        $this->crearEventoBitacora(['empresa_id' => $empresa, 'modulo' => 'creditos', 'accion' => AccionAuditoria::Crear, 'fecha_hora' => '2026-03-15 10:00:00']);
        $this->crearEventoBitacora(['empresa_id' => $empresa, 'modulo' => 'creditos', 'accion' => AccionAuditoria::Aprobar, 'autor_nombre' => 'Otro', 'autor_usuario' => 'otro', 'fecha_hora' => '2026-03-15 10:00:00']);
        $this->crearEventoBitacora(['empresa_id' => $empresa, 'modulo' => 'creditos', 'accion' => AccionAuditoria::Aprobar, 'autor_nombre' => 'Zulema Ortiz', 'entidad' => 'credito', 'fecha_hora' => '2026-04-20 10:00:00']);

        $consulta = http_build_query([
            'modulo' => 'creditos', 'accion' => 'aprobar', 'autor' => 'zulema', 'entidad' => 'credito',
            'desde' => '2026-03-01', 'hasta' => '2026-03-31',
        ]);

        $respuesta = $this->getJson(self::URL.'?'.$consulta)->assertOk();

        $this->assertSame([$objetivo->evento_id], $this->idsDe($respuesta->json()));
    }

    public function test_filtra_por_autor_usuario_id(): void
    {
        $admin = $this->actingAsAdmin();

        $objetivo = $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'usuario_id' => $admin->id]);
        $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'usuario_id' => $admin->id + 999]);

        $respuesta = $this->getJson(self::URL.'?autor_usuario_id='.$admin->id)->assertOk();

        $this->assertSame([$objetivo->evento_id], $this->idsDe($respuesta->json()));
    }

    public function test_el_rango_de_fechas_incluye_todo_el_dia_final(): void
    {
        $admin = $this->actingAsAdmin();
        $ultimoInstante = $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'fecha_hora' => '2026-03-31 23:59:59.500']);
        $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'fecha_hora' => '2026-04-01 00:00:00']);

        $respuesta = $this->getJson(self::URL.'?desde=2026-03-31&hasta=2026-03-31')->assertOk();

        $this->assertSame([$ultimoInstante->evento_id], $this->idsDe($respuesta->json()));
    }

    public function test_ordena_por_fecha_y_hora_descendente_no_por_orden_de_insercion(): void
    {
        $admin = $this->actingAsAdmin();
        $antiguo = $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'fecha_hora' => '2026-01-01 08:00:00']);
        $reciente = $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'fecha_hora' => '2026-01-01 09:00:00']);
        // Insertado después, pero ocurrió antes: llegó tarde al listener.
        $tardio = $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'fecha_hora' => '2026-01-01 07:00:00']);

        $orden = collect($this->getJson(self::URL)->assertOk()->json('data'))->pluck('evento_id')->all();

        $this->assertSame([$reciente->evento_id, $antiguo->evento_id, $tardio->evento_id], $orden);
    }

    public function test_el_listado_expone_los_campos_del_evento_con_tipos_precisos(): void
    {
        $admin = $this->actingAsAdmin();
        $this->crearEventoBitacora([
            'empresa_id' => $admin->empresa_id, 'usuario_id' => $admin->id, 'entidad_id' => 12,
            'valores_anteriores' => ['nombre' => 'A'], 'valores_nuevos' => ['nombre' => 'B'],
            'ip' => '10.0.0.5', 'motivo' => 'Corrección',
        ]);

        $evento = $this->getJson(self::URL)->assertOk()->json('data.0');

        $this->assertSame($admin->empresa_id, $evento['empresa_id']);
        $this->assertSame(12, $evento['entidad_id']);
        $this->assertFalse($evento['es_superadmin']);
        $this->assertSame('crear', $evento['accion']);
        $this->assertSame(['nombre' => 'B'], $evento['valores_nuevos']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}$/', $evento['fecha_hora']);
    }

    public function test_rechaza_filtros_invalidos_con_422(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::URL.'?accion=inventada&desde=2026-05-02&hasta=2026-05-01&per_page=500&empresa_id=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['accion', 'hasta', 'per_page', 'empresa_id']);
    }

    public function test_no_hay_rutas_para_modificar_ni_eliminar_la_bitacora(): void
    {
        $admin = $this->actingAsAdmin();
        $evento = $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id]);

        $this->patchJson(self::URL, ['descripcion' => 'x'])->assertStatus(405);
        $this->putJson(self::URL, [])->assertStatus(405);
        $this->deleteJson(self::URL)->assertStatus(405);
        $this->patchJson(self::URL.'/'.$evento->id, [])->assertStatus(404);
        $this->deleteJson(self::URL.'/'.$evento->id)->assertStatus(404);
    }
}
