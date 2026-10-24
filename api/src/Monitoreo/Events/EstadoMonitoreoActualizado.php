<?php

namespace App\Monitoreo\Events;

use App\Monitoreo\Constants\MonitoreoConstants;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Se emite en el mismo proceso del monitor: encolarlo atrasaría cada muestra lo que tarde la cola. */
class EstadoMonitoreoActualizado implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly array $estado) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(MonitoreoConstants::CANAL)];
    }

    public function broadcastAs(): string
    {
        return 'estado.actualizado';
    }

    public function broadcastWith(): array
    {
        return ['estado' => $this->estado];
    }
}
