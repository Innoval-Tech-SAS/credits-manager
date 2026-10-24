<?php

namespace App\Correo\Constants;

use App\Correo\Enums\TipoCorreo;

class CorreoConstants
{
    public const ENTIDAD_AUDITORIA = 'configuracion_correo';

    /** Conexión y cola explícitas, igual que el resto de workers: no dependen de QUEUE_CONNECTION. */
    public const CONEXION_COLA = 'redis';

    public const COLA = 'correos';

    public const TIMEOUT_JOB_SEGUNDOS = 120;

    /** Tamaño máximo del HTML de la plantilla, en caracteres. */
    public const MAXIMO_CARACTERES_CUERPO = 100000;

    public const PLANTILLA_INICIAL_ASUNTO_COBRANZA = 'Recordatorio de pago - {empresa}';

    public const PLANTILLA_INICIAL_CUERPO_COBRANZA = <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light">
  <title>Recordatorio de cuota</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f5f8; -webkit-text-size-adjust:100%;">

  <!-- Texto de vista previa (preheader) -->
  <div style="display:none; max-height:0; overflow:hidden; opacity:0; font-size:1px; line-height:1px; color:#f3f5f8;">
    Recordatorio: su cuota {cuota} vence el {fecha_vencimiento}.
  </div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f3f5f8" style="background-color:#f3f5f8;">
    <tr>
      <td align="center" style="padding:32px 16px;">

        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e3e8ef;">

          <!-- Encabezado -->
          <tr>
            <td bgcolor="#1f3a5f" style="background-color:#1f3a5f; padding:28px 40px;">
              <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; letter-spacing:1.5px; text-transform:uppercase; color:#a9bcd6;">
                Recordatorio de pago
              </p>
              <h1 style="margin:8px 0 0 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:28px; font-weight:bold; color:#ffffff;">
                {empresa}
              </h1>
            </td>
          </tr>

          <!-- Contenido -->
          <tr>
            <td style="padding:36px 40px 8px 40px; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#2d3748;">
              <p style="margin:0 0 16px 0;">Estimado(a) <strong>{cliente}</strong>:</p>
              <p style="margin:0 0 24px 0;">
                Le recordamos que se acerca la fecha de vencimiento de una de las cuotas de su crédito. A continuación encontrará el detalle:
              </p>
            </td>
          </tr>

          <!-- Detalle de la cuota -->
          <tr>
            <td style="padding:0 40px 8px 40px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e3e8ef; border-radius:6px; background-color:#f8fafc;">
                <tr>
                  <td style="padding:14px 20px; font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#718096; border-bottom:1px solid #e3e8ef;">Crédito</td>
                  <td align="right" style="padding:14px 20px; font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#1a202c; font-weight:bold; border-bottom:1px solid #e3e8ef;">{credito}</td>
                </tr>
                <tr>
                  <td style="padding:14px 20px; font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#718096; border-bottom:1px solid #e3e8ef;">Cuota</td>
                  <td align="right" style="padding:14px 20px; font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#1a202c; font-weight:bold; border-bottom:1px solid #e3e8ef;">{cuota}</td>
                </tr>
                <tr>
                  <td style="padding:14px 20px; font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#718096; border-bottom:1px solid #e3e8ef;">Fecha de vencimiento</td>
                  <td align="right" style="padding:14px 20px; font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#1a202c; font-weight:bold; border-bottom:1px solid #e3e8ef;">{fecha_vencimiento}</td>
                </tr>
                <tr>
                  <td style="padding:18px 20px; font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#718096;">Valor a pagar</td>
                  <td align="right" style="padding:18px 20px; font-family:Arial, Helvetica, sans-serif; font-size:20px; color:#1f3a5f; font-weight:bold;">{valor}</td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Nota final -->
          <tr>
            <td style="padding:24px 40px 36px 40px; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#4a5568;">
              <p style="margin:0 0 16px 0;">
                Si ya realizó el pago, por favor omita este mensaje. Ante cualquier duda, comuníquese con nosotros; con gusto le ayudaremos.
              </p>
              <p style="margin:0;">
                Cordialmente,<br>
                <strong style="color:#1a202c;">{empresa}</strong>
              </p>
            </td>
          </tr>

          <!-- Pie -->
          <tr>
            <td bgcolor="#f8fafc" style="background-color:#f8fafc; padding:20px 40px; border-top:1px solid #e3e8ef; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#8a94a6; text-align:center;">
              Este es un mensaje automático, por favor no responda directamente a este correo.
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>

</body>
</html>
HTML;

    /** Mismo asunto/cuerpo que estaban fijos en código antes de este cambio: la primera plantilla de facturación los conserva. */
    public const PLANTILLA_INICIAL_ASUNTO_FACTURACION = '{tipo_documento} {numero_documento}';

    public const PLANTILLA_INICIAL_CUERPO_FACTURACION = <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<body style="margin:0; padding:32px 16px; background-color:#f3f5f8; font-family:Arial, Helvetica, sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:8px; border:1px solid #e3e8ef;">
        <tr><td style="padding:32px 40px; font-size:15px; line-height:24px; color:#2d3748;">
          <p style="margin:0 0 16px 0;">Adjuntamos el documento solicitado en formato PDF.</p>
          <p style="margin:0; color:#8a94a6; font-size:12px;">Este es un mensaje automático, por favor no responda directamente a este correo.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    /**
     * Variables que cada tipo de plantilla acepta. En el texto van entre llaves: {cliente}.
     * `origen` es de dónde sale el dato, para que el selector de la pantalla lo muestre.
     */
    public static function variables(TipoCorreo $tipo): array
    {
        return match ($tipo) {
            TipoCorreo::Cobranza => [
                'cliente' => ['descripcion' => 'Nombre del cliente', 'origen' => 'terceros.nombre (vía clientes)'],
                'empresa' => ['descripcion' => 'Nombre de la empresa', 'origen' => 'empresas.nombre_empresa'],
                'credito' => ['descripcion' => 'Código del crédito', 'origen' => 'creditos.codigo'],
                'cuota' => ['descripcion' => 'Número de la cuota', 'origen' => 'cuotas.numero_cuota'],
                'valor' => ['descripcion' => 'Valor de la cuota, en pesos', 'origen' => 'cuotas.valor'],
                'fecha_vencimiento' => ['descripcion' => 'Fecha de vencimiento de la cuota', 'origen' => 'cuotas.fecha_vencimiento'],
            ],
            TipoCorreo::Facturacion => [
                'cliente' => ['descripcion' => 'Nombre del cliente', 'origen' => 'terceros.nombre (vía clientes)'],
                'empresa' => ['descripcion' => 'Nombre de la empresa', 'origen' => 'empresas.nombre_empresa'],
                'tipo_documento' => ['descripcion' => 'Factura, nota crédito o nota débito', 'origen' => 'documentos_facturacion.tipo'],
                'numero_documento' => ['descripcion' => 'Número del documento', 'origen' => 'documentos_facturacion.numero'],
                'valor_total' => ['descripcion' => 'Valor total del documento, en pesos', 'origen' => 'lineas_documento_facturacion.valor (suma)'],
                'fecha' => ['descripcion' => 'Fecha de emisión del documento', 'origen' => 'documentos_facturacion.fecha'],
                'fecha_vencimiento' => ['descripcion' => 'Fecha de vencimiento (solo nota débito)', 'origen' => 'documentos_facturacion.fecha_vencimiento'],
            ],
        };
    }

    /** Datos de ejemplo para la vista previa cuando no hay un cliente/documento elegido. */
    public static function ejemploVistaPrevia(TipoCorreo $tipo): array
    {
        return match ($tipo) {
            TipoCorreo::Cobranza => [
                'cliente' => 'Juan Pérez',
                'empresa' => 'Empresa de ejemplo',
                'credito' => 'CR-0001',
                'cuota' => '3',
                'valor' => '$ 350.000,00',
                'fecha_vencimiento' => '15/10/2026',
            ],
            TipoCorreo::Facturacion => [
                'cliente' => 'Juan Pérez',
                'empresa' => 'Empresa de ejemplo',
                'tipo_documento' => 'Factura',
                'numero_documento' => 'F 1',
                'valor_total' => '$ 150.000,00',
                'fecha' => '09/10/2026',
                'fecha_vencimiento' => '25/10/2026',
            ],
        };
    }

    public const MENSAJE_SMTP_FALLIDO = 'No se pudo conectar o autenticar con el servidor de correo. Revise la configuración.';

    public const MENSAJE_ENVIO_FALLIDO = 'No se pudo enviar el correo. Intente de nuevo o revise la configuración.';

    public const MENSAJE_PRUEBA_ENVIADA = 'Correo de prueba enviado. Revise la bandeja de entrada del destinatario.';
}
