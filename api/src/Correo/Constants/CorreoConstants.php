<?php

namespace App\Correo\Constants;

class CorreoConstants
{
    public const ENTIDAD_AUDITORIA = 'configuracion_correo';

    /** Conexión y cola explícitas, igual que el resto de workers: no dependen de QUEUE_CONNECTION. */
    public const CONEXION_COLA = 'redis';

    public const COLA = 'correos';

    public const TIMEOUT_JOB_SEGUNDOS = 120;

    public const PLANTILLA_RECORDATORIO = 'recordatorio_cobranza';

    /** Tamaño máximo del HTML de la plantilla, en caracteres. */
    public const MAXIMO_CARACTERES_CUERPO = 100000;

    /**
     * Texto inicial de la plantilla. Solo se usa para llenar la primera configuración de una empresa:
     * a partir de ahí el texto vive en `configuraciones_correo` y lo edita la empresa.
     */
    public const PLANTILLA_INICIAL_ASUNTO = 'Recordatorio de pago - {empresa}';

    public const PLANTILLA_INICIAL_CUERPO = <<<'HTML'
<p>Estimado(a) {cliente}:</p>
<p>Le recordamos que la cuota {cuota} de su crédito {credito}, por valor de <strong>{valor}</strong>, tiene fecha de vencimiento <strong>{fecha_vencimiento}</strong>.</p>
<p>Si ya realizó el pago, por favor omita este mensaje. Ante cualquier duda, comuníquese con nosotros.</p>
<p>{empresa}</p>
HTML;

    /** Variables que la plantilla acepta. En el texto van entre llaves: {cliente}. */
    public const VARIABLES = [
        'cliente' => 'Nombre del cliente',
        'empresa' => 'Nombre de la empresa',
        'credito' => 'Código del crédito',
        'cuota' => 'Número de la cuota',
        'valor' => 'Valor de la cuota',
        'fecha_vencimiento' => 'Fecha de vencimiento de la cuota',
    ];

    /** Datos de ejemplo para la vista previa cuando no hay un cliente elegido. */
    public const EJEMPLO_VISTA_PREVIA = [
        'cliente' => 'Juan Pérez',
        'empresa' => 'Empresa de ejemplo',
        'credito' => 'CR-0001',
        'cuota' => '3',
        'valor' => '$ 350.000,00',
        'fecha_vencimiento' => '15/10/2026',
    ];

    public const MENSAJE_SMTP_FALLIDO = 'No se pudo conectar o autenticar con el servidor de correo. Revise la configuración.';

    public const MENSAJE_ENVIO_FALLIDO = 'No se pudo enviar el correo. Intente de nuevo o revise la configuración.';

    public const MENSAJE_PRUEBA_ENVIADA = 'Correo de prueba enviado. Revise la bandeja de entrada del destinatario.';

    public const MENSAJE_PRUEBA_REGISTRO = 'Proveedor de prueba: el mensaje se escribió en el log de la aplicación y no salió a internet.';
}
