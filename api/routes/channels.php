<?php

use App\Usuarios\Models\Usuario;
use Illuminate\Support\Facades\Broadcast;

// Canal privado por usuario: solo su dueño se suscribe (notificaciones de exportación, etc.).
Broadcast::channel('usuarios.{id}', fn (Usuario $usuario, int $id) => $usuario->id === $id);
