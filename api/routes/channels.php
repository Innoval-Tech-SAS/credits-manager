<?php

use App\Monitoreo\Constants\MonitoreoConstants;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Facades\Broadcast;

// Canal privado por usuario: solo su dueño se suscribe (notificaciones de exportación, etc.).
Broadcast::channel('usuarios.{id}', fn (Usuario $usuario, int $id) => $usuario->id === $id);

// Panel de salud del sistema: estado del servidor y de los servicios, solo para el superadmin.
Broadcast::channel(MonitoreoConstants::CANAL, fn (Usuario $usuario) => (bool) $usuario->es_superadmin);
