<?php

namespace App\Notificaciones\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Se emite en el mismo proceso (sin pasar por la cola): la llama el worker de exportaciones y no debe encolar otro job. */
class NotificacionCreada implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $usuarioId,
        public readonly array $notificacion,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("usuarios.{$this->usuarioId}")];
    }

    public function broadcastAs(): string
    {
        return 'notificacion.creada';
    }

    public function broadcastWith(): array
    {
        return ['notificacion' => $this->notificacion];
    }
}
