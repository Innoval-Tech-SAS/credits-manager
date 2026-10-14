<?php

namespace Tests\Feature\Dashboard;

use App\Auditoria\Models\BitacoraAuditoria;
use App\Monitoreo\Services\EstadoRespaldosService;
use App\Monitoreo\Services\EstadoServiciosService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class PanelSuperadminTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_sin_autenticar_responde_401(): void
    {
        $this->getJson('/api/v1/panel-superadmin')->assertUnauthorized();
    }

    public function test_el_administrador_de_una_empresa_no_ve_el_panel(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/panel-superadmin')->assertForbidden();
    }

    public function test_el_panel_trae_las_6_cifras_de_plataforma(): void
    {
        $this->actingAsSuperAdmin();

        $this->crearEmpresaDePrueba();
        $this->crearEmpresaDePrueba();

        $this->loginFallido(now()->subDays(2));
        $this->loginFallido(now()->subDays(10)); // fuera de la ventana de 7 días

        $this->sesion(now()->subMinutes(5));
        $this->sesion(now()->subDays(5)); // vencida, no cuenta

        DB::table('ejecuciones_tareas_programadas')->insert([
            ['tarea_clave' => 'metas:evaluar', 'iniciado_en' => now(), 'finalizado_en' => now(), 'resultado' => 'error', 'error' => null],
            ['tarea_clave' => 'metas:evaluar', 'iniciado_en' => now()->subMonthNoOverflow(), 'finalizado_en' => now()->subMonthNoOverflow(), 'resultado' => 'error', 'error' => null],
        ]);

        $respuesta = $this->getJson('/api/v1/panel-superadmin')->assertOk();

        $respuesta->assertJsonPath('logins_fallidos_7_dias', 1)
            ->assertJsonPath('usuarios_activos', 1)
            ->assertJsonPath('empresas_activas', 2)
            ->assertJsonPath('errores_tareas_mes', 1)
            ->assertJsonStructure(['servicios_caidos', 'ultimo_respaldo' => ['horas_desde_ultimo_diario', 'alerta']]);

        // Los servicios y el respaldo ya tienen su propia cobertura en PanelMonitoreoTest: aquí solo se
        // confirma que el panel reproduce fielmente lo que dicen esos mismos servicios, sin recalcular nada.
        $cantidadCaidosEsperada = collect(app(EstadoServiciosService::class)->revisar())->where('estado', 'caido')->count();
        $respaldoEsperado = app(EstadoRespaldosService::class)->resumen(CarbonImmutable::now());

        $respuesta->assertJsonPath('servicios_caidos', $cantidadCaidosEsperada)
            ->assertJsonPath('ultimo_respaldo.alerta', $respaldoEsperado['alerta']);
    }

    public function test_el_panel_trae_la_tendencia_de_logins_y_las_empresas_recientes(): void
    {
        $this->actingAsSuperAdmin();

        $empresaVieja = $this->crearEmpresaDePrueba();
        DB::table('empresas')->where('id', $empresaVieja->id)->update(['creado_en' => now()->subDays(30)]);
        $empresaReciente = $this->crearEmpresaDePrueba();

        $this->loginFallido(now());
        $this->loginExitoso(now());
        $this->loginFallido(now()->subDays(20)); // fuera de la ventana de 14 días

        $respuesta = $this->getJson('/api/v1/panel-superadmin')->assertOk();

        $tendencia = $respuesta->json('tendencia_logins');
        $this->assertCount(14, $tendencia);
        $this->assertSame(now()->toDateString(), $tendencia[13]['fecha']);
        $this->assertSame(1, $tendencia[13]['exitosos']);
        $this->assertSame(1, $tendencia[13]['fallidos']);
        $this->assertSame(0, $tendencia[0]['exitosos'] + $tendencia[0]['fallidos']);

        $respuesta->assertJsonPath('empresas_recientes.0.id', $empresaReciente->id);
    }

    private function loginFallido(\DateTimeInterface $fechaHora): void
    {
        BitacoraAuditoria::query()->create([
            'evento_id' => (string) Str::uuid(),
            'empresa_id' => null,
            'modulo' => 'auth',
            'accion' => 'login_fallido',
            'descripcion' => 'Intento de acceso fallido.',
            'fecha_hora' => $fechaHora,
            'creado_en' => now(),
        ]);
    }

    private function loginExitoso(\DateTimeInterface $fechaHora): void
    {
        BitacoraAuditoria::query()->create([
            'evento_id' => (string) Str::uuid(),
            'empresa_id' => null,
            'modulo' => 'auth',
            'accion' => 'login',
            'descripcion' => 'Inicio de sesión.',
            'fecha_hora' => $fechaHora,
            'creado_en' => now(),
        ]);
    }

    private function sesion(\DateTimeInterface $ultimaActividad): void
    {
        static $contador = 0;
        $contador++;

        DB::table('sessions')->insert([
            'id' => 'sesion-prueba-'.$contador,
            'user_id' => $contador,
            'payload' => '',
            'last_activity' => $ultimaActividad->getTimestamp(),
        ]);
    }
}
