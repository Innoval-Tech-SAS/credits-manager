<?php

namespace App\Notificaciones\Enums;

enum TipoNotificacion: string
{
    case ExportacionLista = 'exportacion_lista';
    case ExportacionFallida = 'exportacion_fallida';
    case ReaperturaSolicitada = 'reapertura_solicitada';
    case ReaperturaAutorizada = 'reapertura_autorizada';
    case ReaperturaRechazada = 'reapertura_rechazada';
}
