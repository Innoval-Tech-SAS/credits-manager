<?php

namespace App\Monitoreo\Constants;

use App\Conciliacion\Constants\ConciliacionConstants;
use App\Correo\Constants\CorreoConstants;
use App\Exportacion\Constants\ExportacionConstants;
use App\Importacion\Constants\ImportacionConstants;

class MonitoreoConstants
{
    public const MODULO = 'monitoreo';

    /** Canal privado del panel de salud: solo el superadmin se suscribe. */
    public const CANAL = 'monitoreo';

    public const COLAS = [
        ExportacionConstants::COLA,
        ImportacionConstants::COLA,
        ConciliacionConstants::COLA,
        CorreoConstants::COLA,
    ];

    /** Servicios que reportan latido: cada worker se llama como su cola, más el listener y el scheduler. */
    public const SERVICIO_LISTENER = 'auditoria-listener';

    public const SERVICIO_SCHEDULER = 'scheduler';

    /** Proceso que publica el estado del panel por Reverb (servicio `monitor` del compose). */
    public const SERVICIO_MONITOR = 'monitor';

    /** Cada cuánto publica el monitor mientras alguien mira el panel. */
    public const INTERVALO_PUBLICACION_SEGUNDOS = 5;

    /** `schedule:work` lanza `schedule:run` una vez por minuto: su latido dura dos vueltas y media. */
    public const VIGENCIA_LATIDO_SCHEDULER_SEGUNDOS = 150;

    public const SUFIJO_WORKER = '-worker';

    public const PREFIJO_LATIDO = 'monitoreo:latido:';

    /** Cada cuánto escribe su latido un proceso, como máximo. */
    public const INTERVALO_LATIDO_SEGUNDOS = 5;

    /** Sin latido en este tiempo el servicio se da por caído. */
    public const VIGENCIA_LATIDO_SEGUNDOS = 30;

    /** Un worker ocupado no late: su latido dura lo que el job más largo (timeout de 3600 s) más un margen. */
    public const VIGENCIA_LATIDO_OCUPADO_SEGUNDOS = 3700;

    public const TIEMPO_ESPERA_TCP_SEGUNDOS = 1.0;

    /** Separación entre las dos lecturas de /proc/stat con las que se calcula el uso de CPU. */
    public const MUESTREO_CPU_MICROSEGUNDOS = 200_000;

    /** Sin un respaldo diario en este tiempo, el panel alerta (corre a diario a la 01:00). */
    public const HORAS_ALERTA_RESPALDO = 26;

    public const RESPALDOS_POR_CLASE = 10;

    public const ERROR_MAXIMO_CARACTERES = 500;
}
