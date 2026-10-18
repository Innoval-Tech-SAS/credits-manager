<?php

namespace App\Respaldos\Services;

use App\Auditoria\Services\EmisorAuditoria;
use App\Respaldos\Models\ConfiguracionRespaldoExterno;
use App\Respaldos\Repositories\ConfiguracionRespaldoExternoRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Arr;
use Throwable;

class ConfiguracionRespaldoExternoService
{
    private const ARCHIVO_PRUEBA = '_prueba_conexion.txt';

    public function __construct(
        protected ConfiguracionRespaldoExternoRepository $configuraciones,
        protected FabricaDiscoRespaldoExterno $fabrica,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function obtener(): array
    {
        return $this->representar($this->configuraciones->obtener());
    }

    /** La clave secreta solo se reemplaza si llega una nueva; omitida, se conserva la guardada. */
    public function guardar(Usuario $usuario, array $datos): array
    {
        $configuracion = $this->configuraciones->obtener();
        $anteriores = $this->auditoria->instantanea($configuracion);
        $claveSecreta = $datos['clave_secreta'] ?? null;

        $configuracion->fill(Arr::except($datos, ['clave_secreta']) + ['actualizado_por' => $usuario->id]);

        if (filled($claveSecreta)) {
            $configuracion->clave_secreta = $claveSecreta;
        }

        $configuracion->save();

        $this->auditoria->registrarModificacion(
            'respaldos',
            'Configuración de respaldo externo modificada'.(filled($claveSecreta) ? ' (clave actualizada)' : ''),
            $configuracion,
            $anteriores,
        );

        return $this->representar($configuracion);
    }

    /** Sube un archivo pequeño al bucket configurado y lo borra, sin esperar a que `activa` esté marcada. */
    public function probar(): array
    {
        $configuracion = $this->configuraciones->obtener();

        if (blank($configuracion->clave_acceso) || blank($configuracion->clave_secreta) || blank($configuracion->bucket)) {
            return ['exitoso' => false, 'mensaje' => 'Completa el bucket y las credenciales antes de probar.'];
        }

        $disco = $this->fabrica->paraConfiguracion($configuracion);

        try {
            $disco->put(self::ARCHIVO_PRUEBA, 'ok '.now()->toIso8601String());
            $disco->delete(self::ARCHIVO_PRUEBA);
        } catch (Throwable) {
            return ['exitoso' => false, 'mensaje' => 'No se pudo conectar con el bucket. Revisa las credenciales, la región y el endpoint.'];
        }

        return ['exitoso' => true, 'mensaje' => 'Conexión correcta: se pudo escribir y borrar un archivo de prueba en el bucket.'];
    }

    /** Nunca incluye la clave secreta: solo indica si está guardada. */
    private function representar(ConfiguracionRespaldoExterno $configuracion): array
    {
        return [
            'activa' => $configuracion->activa,
            'bucket' => $configuracion->bucket,
            'region' => $configuracion->region,
            'endpoint' => $configuracion->endpoint,
            'uso_path_style' => $configuracion->uso_path_style,
            'clave_acceso' => $configuracion->clave_acceso,
            'clave_secreta_estado' => filled($configuracion->getRawOriginal('clave_secreta')) ? 'configurada' : 'sin_configurar',
        ];
    }
}
