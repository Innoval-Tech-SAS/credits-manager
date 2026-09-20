<?php

namespace Tests\Feature\Notificaciones;

use App\Empresas\Models\Empresa;
use App\Notificaciones\Enums\TipoNotificacion;
use App\Notificaciones\Events\NotificacionCreada;
use App\Notificaciones\Services\NotificacionService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class NotificacionTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private function notificar(int $usuarioId, ?int $empresaId, string $titulo = 'Exportación lista'): int
    {
        return app(NotificacionService::class)
            ->notificar($usuarioId, $empresaId, TipoNotificacion::ExportacionLista, $titulo, 'Tu archivo está listo.', ['exportacion_id' => 3])->id;
    }

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->getJson('/api/v1/notificaciones')->assertStatus(401);
        $this->postJson('/api/v1/notificaciones/leer-todas')->assertStatus(401);
        $this->postJson('/api/v1/notificaciones/1/leer')->assertStatus(401);
    }

    public function test_lista_las_propias_con_el_contador_de_no_leidas(): void
    {
        $admin = $this->actingAsAdmin();
        $this->notificar($admin->id, $admin->empresa_id, 'Primera');
        $leida = $this->notificar($admin->id, $admin->empresa_id, 'Segunda');
        $this->postJson("/api/v1/notificaciones/{$leida}/leer")->assertOk();

        $respuesta = $this->getJson('/api/v1/notificaciones')->assertOk();

        $respuesta->assertJsonCount(2, 'data')->assertJsonPath('no_leidas', 1)->assertJsonPath('data.0.titulo', 'Segunda');
        $this->assertSame('exportacion_lista', $respuesta->json('data.0.tipo'));
        $this->assertSame(['exportacion_id' => 3], $respuesta->json('data.0.datos'));
        $this->assertNotNull($respuesta->json('data.0.leida_en'));
        $this->assertNull($respuesta->json('data.1.leida_en'));
    }

    public function test_marcar_todas_como_leidas(): void
    {
        $admin = $this->actingAsAdmin();
        $this->notificar($admin->id, $admin->empresa_id);
        $this->notificar($admin->id, $admin->empresa_id);

        $this->postJson('/api/v1/notificaciones/leer-todas')->assertNoContent();

        $this->getJson('/api/v1/notificaciones')->assertJsonPath('no_leidas', 0);
    }

    public function test_no_ve_ni_marca_notificaciones_de_otro_usuario_ni_de_otra_empresa(): void
    {
        $admin = $this->actingAsAdmin();
        $companero = $this->actingAsAsesor(Empresa::find($admin->empresa_id));
        $ajena = $this->actingAsAdmin();
        $idAjena = $this->notificar($ajena->id, $ajena->empresa_id);
        $idDelCompanero = $this->notificar($companero->id, $companero->empresa_id);

        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/v1/notificaciones')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/notificaciones/{$idAjena}/leer")->assertStatus(404);
        $this->postJson("/api/v1/notificaciones/{$idDelCompanero}/leer")->assertStatus(404);

        $this->postJson('/api/v1/notificaciones/leer-todas')->assertNoContent();
        $this->assertDatabaseHas('notificaciones', ['id' => $idAjena, 'leida_en' => null]);
        $this->assertDatabaseHas('notificaciones', ['id' => $idDelCompanero, 'leida_en' => null]);
    }

    public function test_una_notificacion_inexistente_da_404(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/notificaciones/9999/leer')->assertStatus(404);
    }

    public function test_al_notificar_se_emite_el_evento_por_websocket_en_el_canal_privado_del_usuario(): void
    {
        Event::fake([NotificacionCreada::class]);
        $admin = $this->actingAsAdmin();

        $id = $this->notificar($admin->id, $admin->empresa_id);

        Event::assertDispatched(NotificacionCreada::class, function (NotificacionCreada $evento) use ($admin, $id) {
            $canal = $evento->broadcastOn()[0];

            return $evento->usuarioId === $admin->id
                && $canal instanceof PrivateChannel
                && $canal->name === "private-usuarios.{$admin->id}"
                && $evento->broadcastAs() === 'notificacion.creada'
                && $evento->broadcastWith()['notificacion']['id'] === $id
                && array_key_exists('leida_en', $evento->broadcastWith()['notificacion'])
                && $evento->broadcastWith()['notificacion']['leida_en'] === null;
        });
    }

    public function test_si_el_websocket_falla_la_notificacion_queda_guardada(): void
    {
        Event::listen(NotificacionCreada::class, function () {
            throw new \RuntimeException('Reverb caído');
        });
        $admin = $this->actingAsAdmin();

        $id = $this->notificar($admin->id, $admin->empresa_id);

        $this->assertDatabaseHas('notificaciones', ['id' => $id]);
    }

    public function test_solo_el_dueno_se_autoriza_en_su_canal_privado(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'clave-de-prueba',
            'broadcasting.connections.reverb.secret' => 'secreto-de-prueba',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);
        app('Illuminate\Broadcasting\BroadcastManager')->forgetDrivers();
        // Los canales se registran sobre el driver activo al arrancar (`null` en tests): se recargan en el nuevo.
        require base_path('routes/channels.php');

        $admin = $this->actingAsAdmin();
        $otro = $this->actingAsAdmin();
        $this->actingAs($admin, 'sanctum');

        $propio = $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => "private-usuarios.{$admin->id}"]);
        $ajeno = $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => "private-usuarios.{$otro->id}"]);

        $propio->assertOk()->assertJsonStructure(['auth']);
        $ajeno->assertStatus(403);
    }

    public function test_el_endpoint_de_autorizacion_del_socket_exige_sesion(): void
    {
        $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-usuarios.1'])->assertStatus(401);
    }
}
