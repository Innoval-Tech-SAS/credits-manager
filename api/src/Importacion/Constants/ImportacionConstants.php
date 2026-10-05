<?php

namespace App\Importacion\Constants;

class ImportacionConstants
{
    public const DISCO = 'importaciones';

    /** Conexión de cola explícita, igual que exportaciones: no depende de QUEUE_CONNECTION. */
    public const CONEXION_COLA = 'redis';

    public const COLA = 'importaciones';

    public const TIMEOUT_JOB_SEGUNDOS = 3600;

    /** Crear empleadores faltantes usa el mismo permiso que el alta de empleadores (`POST /empleadores`). */
    public const PERMISO_EMPLEADORES = ['clientes', 'crear'];

    /** Contabilizar (asiento de apertura y recaudos) usa el permiso de comprobantes manuales. */
    public const PERMISO_CONTABILIZAR = ['contabilidad', 'crear_manual'];

    public const ACCION_CONTABILIZAR = 'crear_manual';

    public const ESTADO_PENDIENTE_DECISION = 'pendiente_decision';

    public const MOTIVO_ERROR_INESPERADO = 'No se pudo procesar el archivo por un error inesperado.';
}
