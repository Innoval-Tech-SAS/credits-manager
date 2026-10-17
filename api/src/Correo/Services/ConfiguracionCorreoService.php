<?php

namespace App\Correo\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Correo\Constants\CorreoConstants;
use App\Correo\Enums\CifradoCorreo;
use App\Correo\Enums\TipoCorreo;
use App\Correo\Mail\CorreoCobranza;
use App\Correo\Models\ConfiguracionCorreo;
use App\Correo\Repositories\ConfiguracionCorreoRepository;
use App\Correo\Support\ContextoFalloCorreo;
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
        protected FabricaMailerCobranza $fabrica,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function obtener(Usuario $usuario, TipoCorreo $tipo): array
    {
        return $this->representar($this->configuraciones->deEmpresaYTipo((int) $usuario->empresa_id, $tipo));
    }

    /** Una fila heredada del driver de prueba (sin host) se trata como si no existiera. */
    private function estaLista(?ConfiguracionCorreo $configuracion): bool
    {
        return $configuracion !== null && filled($configuracion->host);
    }

    /** La clave solo se reemplaza si llega una nueva; omitida, se conserva la guardada. */
    public function guardar(Usuario $usuario, TipoCorreo $tipo, array $datos): array
    {
        $empresaId = (int) $usuario->empresa_id;
        $configuracion = $this->configuraciones->deEmpresaYTipo($empresaId, $tipo);
        $esNueva = $configuracion === null;

        if ($esNueva) {
            $configuracion = new ConfiguracionCorreo;
            $configuracion->empresa_id = $empresaId;
            $configuracion->tipo = $tipo;
        }

        $anteriores = $esNueva ? [] : $this->auditoria->instantanea($configuracion);
        $clave = $datos['clave'] ?? null;

        $configuracion->fill(Arr::except($datos, ['clave']) + ['actualizado_por' => $usuario->id]);

        if (filled($clave)) {
            $configuracion->clave = $clave;
        }

        $configuracion->save();

        $this->auditar($esNueva, $configuracion, $anteriores, filled($clave));

        return $this->representar($configuracion);
    }

    /** Envío síncrono de prueba con la configuración guardada. Devuelve el resultado sin credenciales. */
    public function enviarPrueba(Usuario $usuario, TipoCorreo $tipo, string $destinatario): array
    {
        $configuracion = $this->configuraciones->deEmpresaYTipo((int) $usuario->empresa_id, $tipo);

        if (! $this->estaLista($configuracion)) {
            throw ValidationException::withMessages(['destinatario' => __('validation.custom.correo.sin_configurar')]);
        }

        $inicio = hrtime(true);

        try {
            $this->fabrica->paraConfiguracion($configuracion)->send(new CorreoCobranza(
                asunto: 'Prueba de configuración de correo',
                cuerpoHtml: '<p>Este es un correo de prueba de la configuración de '.$tipo->value.'.</p><p>Si lo recibe, el servidor de correo funciona.</p>',
                destinatario: $destinatario,
                remitente: $configuracion->remitente,
                nombreRemitente: $configuracion->nombre_remitente,
            ));
        } catch (Throwable $excepcion) {
            Log::warning('Prueba de correo fallida', [
                'empresa_id' => $configuracion->empresa_id,
                'tipo' => $tipo->value,
                ...ContextoFalloCorreo::construir($configuracion, $excepcion, (hrtime(true) - $inicio) / 1_000_000),
            ]);

            return ['enviado' => false, 'mensaje' => $this->mensajeDeFallo($excepcion)];
        }

        return ['enviado' => true, 'mensaje' => CorreoConstants::MENSAJE_PRUEBA_ENVIADA];
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
            .' ('.$configuracion->tipo->value.')'
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

    /** Nunca incluye la clave: solo indica si está guardada. Una fila incompleta (sin host) se muestra como sin configurar. */
    private function representar(?ConfiguracionCorreo $configuracion): array
    {
        $configuracion = $this->estaLista($configuracion) ? $configuracion : null;

        return [
            'estado' => $configuracion ? 'configurada' : 'sin_configurar',
            'clave_estado' => $configuracion && filled($configuracion->getRawOriginal('clave')) ? 'configurada' : 'sin_configurar',
            'remitente' => $configuracion?->remitente,
            'nombre_remitente' => $configuracion?->nombre_remitente,
            'host' => $configuracion?->host,
            'puerto' => $configuracion?->puerto,
            'cifrado' => $configuracion?->cifrado->value ?? CifradoCorreo::Tls->value,
            'usuario' => $configuracion?->usuario,
        ];
    }
}
