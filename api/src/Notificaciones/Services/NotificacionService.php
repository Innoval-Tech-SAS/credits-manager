<?php

namespace App\Notificaciones\Services;

use App\Notificaciones\Enums\TipoNotificacion;
use App\Notificaciones\Events\NotificacionCreada;
use App\Notificaciones\Models\Notificacion;
use App\Notificaciones\Repositories\NotificacionRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificacionService
{
    private const LIMITE_RECIENTES = 20;

    public function __construct(protected NotificacionRepository $notificaciones) {}

    /**
     * Persiste y empuja por WebSocket. Corre también en el worker (sin sesión): usuario y empresa
     * llegan explícitos. Si el socket falla, la notificación ya está guardada y se ve al abrir la app.
     */
    public function notificar(int $usuarioId, ?int $empresaId, TipoNotificacion $tipo, string $titulo, string $mensaje, array $datos = []): Notificacion
    {
        /** @var Notificacion $notificacion */
        $notificacion = $this->notificaciones->crear([
            'empresa_id' => $empresaId,
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'datos' => $datos ?: null,
        ]);

        try {
            // `leida_en` no se hidrata tras el create (default null de la BD): el cliente lo necesita explícito para contarla como no leída.
            NotificacionCreada::dispatch($usuarioId, ['leida_en' => null] + $notificacion->toArray());
        } catch (Throwable $excepcion) {
            Log::warning('No se pudo emitir la notificación por WebSocket.', ['notificacion_id' => $notificacion->id, 'error' => $excepcion->getMessage()]);
        }

        return $notificacion;
    }

    /** @return array{data: mixed, no_leidas: int} */
    public function listar(Usuario $usuario): array
    {
        return [
            'data' => $this->notificaciones->recientesDelUsuario($usuario->id, self::LIMITE_RECIENTES),
            'no_leidas' => $this->notificaciones->contarNoLeidas($usuario->id),
        ];
    }

    public function marcarLeida(int $id, Usuario $usuario): Notificacion
    {
        $notificacion = $this->notificaciones->buscarDelUsuarioOFallar($id, $usuario->id);

        return $notificacion->leida_en ? $notificacion : $this->notificaciones->actualizar($notificacion, ['leida_en' => now()]);
    }

    public function marcarTodasLeidas(Usuario $usuario): void
    {
        $this->notificaciones->marcarTodasLeidas($usuario->id);
    }
}
