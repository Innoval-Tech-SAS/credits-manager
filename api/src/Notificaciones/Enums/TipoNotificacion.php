<?php

namespace App\Notificaciones\Enums;

enum TipoNotificacion: string
{
    case ExportacionLista = 'exportacion_lista';
    case ExportacionFallida = 'exportacion_fallida';
}
