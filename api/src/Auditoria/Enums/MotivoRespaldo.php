<?php

namespace App\Auditoria\Enums;

/** Por qué un evento terminó en el log de respaldo en lugar de llegar a la tabla. */
enum MotivoRespaldo: string
{
    case RedisNoDisponible = 'redis_no_disponible';
    case EventoInvalido = 'evento_invalido';
    case PayloadInvalido = 'payload_invalido';
    case ErrorInsercion = 'error_insercion';
}
