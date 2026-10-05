<?php

namespace App\Correo\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Correo\Constants\CorreoConstants;
use App\Correo\Enums\CifradoCorreo;
use App\Correo\Enums\DriverCorreo;
use App\Correo\Mail\CorreoCobranza;
use App\Correo\Models\ConfiguracionCorreo;
use App\Correo\Repositories\ConfiguracionCorreoRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class ConfiguracionCorreoService
{
    public function __construct(
        protected ConfiguracionCorreoRepository $configuraciones,
        protected PlantillaCorreoService $plantillas,
        protected FabricaMailerCobranza $fabrica,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function obtener(Usuario $usuario): array
    {
        return $this->representar($this->configuraciones->deEmpresa((int) $usuario->empresa_id));
    }

    /** La clave solo se reemplaza si llega una nueva; omitida, se conserva la guardada. */
    public function guardar(Usuario $usuario, array $datos): array
    {
        $empresaId = (int) $usuario->empresa_id;
        $configuracion = $this->configuraciones->deEmpresa($empresaId);
        $esNueva = $configuracion === null;

        if ($esNueva) {
            $configuracion = new ConfiguracionCorreo;
            $configuracion->empresa_id = $empresaId;
        }

        $anteriores = $esNueva ? [] : $this->auditoria->instantanea($configuracion);
        $clave = $datos['clave'] ?? null;

        $datos['plantilla_cuerpo'] = $this->plantillas->sanitizarHtml($datos['plantilla_cuerpo']);
        $configuracion->fill(Arr::except($datos, ['clave']) + ['actualizado_por' => $usuario->id]);

        if (filled($clave)) {
            $configuracion->clave = $clave;
        }

        $configuracion->save();

        $this->auditar($esNueva, $configuracion, $anteriores, filled($clave));

        return $this->representar($configuracion);
    }

    /** Vista previa con datos de ejemplo: no toca la base ni envía nada. */
    public function vistaPrevia(string $asunto, string $cuerpo): array
    {
        return [
            'asunto' => $this->plantillas->renderizarAsunto($asunto, CorreoConstants::EJEMPLO_VISTA_PREVIA),
            'cuerpo' => $this->plantillas->renderizarCuerpo($this->plantillas->sanitizarHtml($cuerpo), CorreoConstants::EJEMPLO_VISTA_PREVIA),
        ];
    }

    /** Envío síncrono de prueba con la configuración guardada. Devuelve el resultado sin credenciales. */
    public function enviarPrueba(Usuario $usuario, string $destinatario): array
    {
        $configuracion = $this->configuraciones->deEmpresa((int) $usuario->empresa_id);

        if ($configuracion === null) {
            throw ValidationException::withMessages(['destinatario' => __('validation.custom.correo.sin_configurar')]);
        }

        try {
            $this->fabrica->paraConfiguracion($configuracion)->send(new CorreoCobranza(
                asunto: 'Prueba de configuración de correo',
                cuerpoHtml: '<p>Este es un correo de prueba de la configuración de cobranza.</p><p>Si lo recibe, el servidor de correo funciona.</p>',
                destinatario: $destinatario,
                remitente: $configuracion->remitente,
                nombreRemitente: $configuracion->nombre_remitente,
            ));
        } catch (Throwable $excepcion) {
            Log::warning('Prueba de correo de cobranza fallida', ['empresa_id' => $configuracion->empresa_id, 'excepcion' => $excepcion::class]);

            return ['enviado' => false, 'mensaje' => $this->mensajeDeFallo($excepcion)];
        }

        return [
            'enviado' => true,
            'mensaje' => $configuracion->driver === DriverCorreo::Registro
                ? CorreoConstants::MENSAJE_PRUEBA_REGISTRO
                : CorreoConstants::MENSAJE_PRUEBA_ENVIADA,
        ];
    }

    private function mensajeDeFallo(Throwable $excepcion): string
    {
        return $excepcion instanceof TransportExceptionInterface
            ? CorreoConstants::MENSAJE_SMTP_FALLIDO
            : CorreoConstants::MENSAJE_ENVIO_FALLIDO;
    }

    /** Sin clave ni valor de clave: solo se indica si cambió. */
    private function auditar(bool $esNueva, ConfiguracionCorreo $configuracion, array $anteriores, bool $claveActualizada): void
    {
        $nuevos = $this->auditoria->instantanea($configuracion);
        $cambiados = array_keys(array_diff_assoc($nuevos, $anteriores));

        if (! $esNueva && $cambiados === [] && ! $claveActualizada) {
            return;
        }

        $descripcion = ($esNueva ? 'Configuración de correo creada' : 'Configuración de correo modificada')
            .($claveActualizada ? ' (clave actualizada)' : '');

        $this->auditoria->registrar(
            $esNueva ? AccionAuditoria::Crear : AccionAuditoria::Modificar,
            AuditoriaConstants::MODULO_CORREO,
            $descripcion,
            CorreoConstants::ENTIDAD_AUDITORIA,
            $configuracion->id,
            anteriores: array_intersect_key($anteriores, array_flip($cambiados)),
            nuevos: array_intersect_key($nuevos, array_flip($cambiados)),
        );
    }

    /** Nunca incluye la clave: solo indica si está guardada. */
    private function representar(?ConfiguracionCorreo $configuracion): array
    {
        return [
            'estado' => $configuracion ? 'configurada' : 'sin_configurar',
            'clave_estado' => $configuracion && filled($configuracion->getRawOriginal('clave')) ? 'configurada' : 'sin_configurar',
            'driver' => $configuracion?->driver->value ?? DriverCorreo::Smtp->value,
            'remitente' => $configuracion?->remitente,
            'nombre_remitente' => $configuracion?->nombre_remitente,
            'host' => $configuracion?->host,
            'puerto' => $configuracion?->puerto,
            'cifrado' => $configuracion?->cifrado->value ?? CifradoCorreo::Tls->value,
            'usuario' => $configuracion?->usuario,
            'plantilla' => [
                'asunto' => $configuracion?->plantilla_asunto ?? CorreoConstants::PLANTILLA_INICIAL_ASUNTO,
                'cuerpo' => $configuracion?->plantilla_cuerpo ?? CorreoConstants::PLANTILLA_INICIAL_CUERPO,
            ],
            'variables' => collect(CorreoConstants::VARIABLES)
                ->map(fn (string $descripcion, string $clave) => ['clave' => $clave, 'descripcion' => $descripcion])
                ->values()
                ->all(),
        ];
    }
}
