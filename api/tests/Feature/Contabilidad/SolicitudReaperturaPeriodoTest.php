<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\EstadoPeriodo;
use App\Contabilidad\Models\PeriodoContable;
use App\Contabilidad\Models\SolicitudReaperturaPeriodo;
use App\Empresas\Models\Empresa;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\TestCase;

class SolicitudReaperturaPeriodoTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesAuditoriaFixtures;
    use RefreshDatabase;

    private Empresa $empresa;

    private Usuario $admin;

    private Usuario $contador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->crearEmpresaDePrueba();
        $this->admin = $this->actingAsAdmin($this->empresa);
        $this->contador = $this->actingAsRolConPermisos('contador', ['configuracion_contable.listar', 'configuracion_contable.cerrar_periodo'], $this->empresa);
    }

    private function periodoCerrado(int $mes = 1): PeriodoContable
    {
        return PeriodoContable::create(['anio' => 2026, 'mes' => $mes, 'estado' => EstadoPeriodo::Cerrado]);
    }

    private function solicitar(PeriodoContable $periodo, string $motivo = 'Corregir un asiento del cierre'): int
    {
        return $this->postJson("/api/v1/contabilidad/periodos/{$periodo->id}/solicitar-reapertura", ['motivo' => $motivo])
            ->assertCreated()
            ->json('solicitud.id');
    }

    public function test_el_contador_solicita_la_reapertura_de_un_periodo_cerrado(): void
    {
        $periodo = $this->periodoCerrado();

        $respuesta = $this->postJson("/api/v1/contabilidad/periodos/{$periodo->id}/solicitar-reapertura", ['motivo' => 'Corregir un asiento']);

        $respuesta->assertCreated()->assertJsonPath('solicitud.estado', 'pendiente')->assertJsonPath('solicitud.periodo.mes', 1);
        $this->assertDatabaseHas('solicitudes_reapertura_periodo', [
            'id' => $respuesta->json('solicitud.id'),
            'empresa_id' => $this->empresa->id,
            'periodo_id' => $periodo->id,
            'solicitado_por' => $this->contador->id,
            'estado' => 'pendiente',
            'motivo' => 'Corregir un asiento',
        ]);
        $this->assertEventoAuditoria('crear', 'contabilidad', 'solicitud_reapertura_periodo', $respuesta->json('solicitud.id'));
    }

    public function test_la_solicitud_avisa_al_administrador_y_no_al_solicitante(): void
    {
        $this->solicitar($this->periodoCerrado());

        $this->assertDatabaseHas('notificaciones', ['usuario_id' => $this->admin->id, 'tipo' => 'reapertura_solicitada']);
        $this->assertDatabaseMissing('notificaciones', ['usuario_id' => $this->contador->id]);
    }

    public function test_solicitar_exige_el_motivo(): void
    {
        $periodo = $this->periodoCerrado();

        $this->postJson("/api/v1/contabilidad/periodos/{$periodo->id}/solicitar-reapertura", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');
    }

    public function test_no_se_puede_solicitar_la_reapertura_de_un_periodo_abierto(): void
    {
        $abierto = PeriodoContable::create(['anio' => 2026, 'mes' => 2, 'estado' => EstadoPeriodo::Abierto]);

        $this->postJson("/api/v1/contabilidad/periodos/{$abierto->id}/solicitar-reapertura", ['motivo' => 'Ya está abierto'])
            ->assertStatus(422)
            ->assertJsonPath('errors.periodo.0', 'Solo se puede solicitar la reapertura de un período cerrado.');
    }

    public function test_no_se_puede_duplicar_una_solicitud_pendiente_del_mismo_periodo(): void
    {
        $periodo = $this->periodoCerrado();
        $this->solicitar($periodo);

        $this->postJson("/api/v1/contabilidad/periodos/{$periodo->id}/solicitar-reapertura", ['motivo' => 'Otra vez'])
            ->assertStatus(422)
            ->assertJsonPath('errors.periodo.0', 'Este período ya tiene una solicitud de reapertura pendiente.');

        $this->assertDatabaseCount('solicitudes_reapertura_periodo', 1);
    }

    public function test_sin_autenticar_ningun_endpoint_responde(): void
    {
        $periodo = $this->periodoCerrado();
        $id = $this->solicitar($periodo);
        $this->salirDeSesion();

        $this->getJson('/api/v1/contabilidad/solicitudes-reapertura')->assertUnauthorized();
        $this->postJson("/api/v1/contabilidad/periodos/{$periodo->id}/solicitar-reapertura", ['motivo' => 'x'])->assertUnauthorized();
        $this->postJson("/api/v1/contabilidad/solicitudes-reapertura/{$id}/autorizar")->assertUnauthorized();
        $this->postJson("/api/v1/contabilidad/solicitudes-reapertura/{$id}/rechazar", ['motivo_resolucion' => 'x'])->assertUnauthorized();
    }

    public function test_sin_el_permiso_correspondiente_responde_403(): void
    {
        $periodo = $this->periodoCerrado();
        $id = $this->solicitar($periodo);

        // El contador puede pedir la reapertura, pero no resolverla.
        $this->postJson("/api/v1/contabilidad/solicitudes-reapertura/{$id}/autorizar")->assertForbidden();
        $this->postJson("/api/v1/contabilidad/solicitudes-reapertura/{$id}/rechazar", ['motivo_resolucion' => 'x'])->assertForbidden();

        // Un asesor sin permisos de contabilidad no puede ni pedirla ni verla.
        $this->actingAsAsesor($this->empresa);
        $this->getJson('/api/v1/contabilidad/solicitudes-reapertura')->assertForbidden();
        $this->postJson("/api/v1/contabilidad/periodos/{$periodo->id}/solicitar-reapertura", ['motivo' => 'x'])->assertForbidden();
    }

    public function test_un_periodo_o_una_solicitud_inexistente_responde_404(): void
    {
        $this->postJson('/api/v1/contabilidad/periodos/99999/solicitar-reapertura', ['motivo' => 'x'])->assertNotFound();

        $this->actingAs($this->admin, 'sanctum');
        $this->postJson('/api/v1/contabilidad/solicitudes-reapertura/99999/autorizar')->assertNotFound();
        $this->postJson('/api/v1/contabilidad/solicitudes-reapertura/99999/rechazar', ['motivo_resolucion' => 'x'])->assertNotFound();
    }

    public function test_el_administrador_autoriza_y_el_periodo_se_reabre_con_el_motivo_del_solicitante(): void
    {
        $periodo = $this->periodoCerrado();
        $id = $this->solicitar($periodo, 'Corregir un asiento del cierre');

        $this->actingAs($this->admin, 'sanctum');
        $this->postJson("/api/v1/contabilidad/solicitudes-reapertura/{$id}/autorizar")
            ->assertOk()
            ->assertJsonPath('solicitud.estado', 'autorizada')
            ->assertJsonPath('solicitud.resolutor.id', $this->admin->id);

        $this->assertSame(EstadoPeriodo::Abierto, $periodo->fresh()->estado);
        $this->assertSame('Corregir un asiento del cierre', $periodo->fresh()->reabierto_motivo);
        $this->assertDatabaseHas('solicitudes_reapertura_periodo', ['id' => $id, 'estado' => 'autorizada', 'resuelto_por' => $this->admin->id]);
        $this->assertNotNull(SolicitudReaperturaPeriodo::findOrFail($id)->resuelto_en);

        $this->assertEventoAuditoria('aprobar', 'contabilidad', 'solicitud_reapertura_periodo', $id);
        $this->assertEventoAuditoria('reabrir_periodo', 'contabilidad', 'periodo_contable', $periodo->id);
        $this->assertDatabaseHas('notificaciones', ['usuario_id' => $this->contador->id, 'tipo' => 'reapertura_autorizada']);
    }

    public function test_el_administrador_rechaza_con_un_motivo_y_el_periodo_sigue_cerrado(): void
    {
        $periodo = $this->periodoCerrado();
        $id = $this->solicitar($periodo);

        $this->actingAs($this->admin, 'sanctum');
        $this->postJson("/api/v1/contabilidad/solicitudes-reapertura/{$id}/rechazar", [])->assertStatus(422)->assertJsonValidationErrors('motivo_resolucion');

        $this->postJson("/api/v1/contabilidad/solicitudes-reapertura/{$id}/rechazar", ['motivo_resolucion' => 'El cierre ya fue presentado'])
            ->assertOk()
            ->assertJsonPath('solicitud.estado', 'rechazada');

        $this->assertSame(EstadoPeriodo::Cerrado, $periodo->fresh()->estado);
        $this->assertDatabaseHas('solicitudes_reapertura_periodo', ['id' => $id, 'estado' => 'rechazada', 'motivo_resolucion' => 'El cierre ya fue presentado']);
        $this->assertEventoAuditoria('rechazar', 'contabilidad', 'solicitud_reapertura_periodo', $id);
        $this->assertDatabaseHas('notificaciones', ['usuario_id' => $this->contador->id, 'tipo' => 'reapertura_rechazada']);
    }

    public function test_una_solicitud_resuelta_no_se_puede_resolver_de_nuevo(): void
    {
        $periodo = $this->periodoCerrado();
        $id = $this->solicitar($periodo);
        $this->actingAs($this->admin, 'sanctum');
        $this->postJson("/api/v1/contabilidad/solicitudes-reapertura/{$id}/rechazar", ['motivo_resolucion' => 'No'])->assertOk();

        $this->postJson("/api/v1/contabilidad/solicitudes-reapertura/{$id}/autorizar")
            ->assertStatus(422)
            ->assertJsonPath('errors.solicitud.0', 'Esta solicitud ya fue resuelta.');

        $this->assertSame(EstadoPeriodo::Cerrado, $periodo->fresh()->estado);
    }

    public function test_no_se_puede_autorizar_si_el_periodo_ya_fue_reabierto_por_otra_via(): void
    {
        $periodo = $this->periodoCerrado();
        $id = $this->solicitar($periodo);

        $this->actingAs($this->admin, 'sanctum');
        $this->postJson("/api/v1/contabilidad/periodos/{$periodo->id}/reabrir", ['motivo' => 'Reapertura directa'])->assertOk();

        $this->postJson("/api/v1/contabilidad/solicitudes-reapertura/{$id}/autorizar")
            ->assertStatus(422)
            ->assertJsonPath('errors.periodo.0', 'El período ya está abierto; rechaza la solicitud.');
    }

    public function test_el_administrador_ve_todas_las_solicitudes_y_el_contador_solo_las_suyas(): void
    {
        $this->solicitar($this->periodoCerrado(1), 'Del contador uno');

        $otroContador = $this->actingAsRolConPermisos('contador2', ['configuracion_contable.listar', 'configuracion_contable.cerrar_periodo'], $this->empresa);
        $this->solicitar($this->periodoCerrado(2), 'Del contador dos');

        $this->actingAs($this->admin, 'sanctum');
        $this->assertCount(2, $this->getJson('/api/v1/contabilidad/solicitudes-reapertura')->assertOk()->json('solicitudes'));

        $this->actingAs($otroContador, 'sanctum');
        $propias = $this->getJson('/api/v1/contabilidad/solicitudes-reapertura')->assertOk()->json('solicitudes');
        $this->assertCount(1, $propias);
        $this->assertSame('Del contador dos', $propias[0]['motivo']);
    }

    public function test_una_empresa_no_ve_ni_resuelve_las_solicitudes_de_otra(): void
    {
        $periodo = $this->periodoCerrado();
        $id = $this->solicitar($periodo);

        $adminAjeno = $this->actingAsAdmin($this->crearEmpresaDePrueba());

        $this->assertSame([], $this->getJson('/api/v1/contabilidad/solicitudes-reapertura')->assertOk()->json('solicitudes'));
        $this->postJson("/api/v1/contabilidad/solicitudes-reapertura/{$id}/autorizar")->assertNotFound();
        $this->postJson("/api/v1/contabilidad/solicitudes-reapertura/{$id}/rechazar", ['motivo_resolucion' => 'x'])->assertNotFound();

        $this->actingAs($adminAjeno, 'sanctum');
        $this->postJson("/api/v1/contabilidad/periodos/{$periodo->id}/solicitar-reapertura", ['motivo' => 'x'])->assertNotFound();

        $this->assertDatabaseHas('solicitudes_reapertura_periodo', ['id' => $id, 'estado' => 'pendiente']);
        $this->assertSame(EstadoPeriodo::Cerrado, $periodo->fresh()->estado);
    }
}
