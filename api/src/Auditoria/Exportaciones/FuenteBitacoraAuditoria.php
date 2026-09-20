<?php

namespace App\Auditoria\Exportaciones;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Data\FiltrosBitacora;
use App\Auditoria\Data\Visibilidad;
use App\Auditoria\Models\BitacoraAuditoria;
use App\Auditoria\Repositories\BitacoraAuditoriaRepository;
use App\Auditoria\Services\AccesoAuditoria;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Enums\TipoColumna;
use App\Usuarios\Models\Usuario;

/**
 * Primer consumidor del módulo Exportacion. Usa `consultaVisible` del repositorio —la misma que el
 * listado—, así que exportar nunca muestra más de lo que se ve en pantalla. Serializable: solo lleva
 * visibilidad, filtros y el `id` de corte (foto consistente del instante de la solicitud: lo que
 * llegue a la bitácora mientras el job espera en la cola no entra al archivo).
 */
final class FuenteBitacoraAuditoria implements FuenteExportable
{
    public function __construct(
        private readonly Visibilidad $visibilidad,
        private readonly FiltrosBitacora $filtros,
        private readonly ?int $hastaId = null,
    ) {}

    public function reporte(): string
    {
        return 'auditoria.bitacora';
    }

    public function modulo(): string
    {
        return AuditoriaConstants::MODULO;
    }

    public function titulo(): string
    {
        return 'Bitácora de auditoría';
    }

    public function nombreArchivo(): string
    {
        return 'bitacora-auditoria';
    }

    public function columnas(): array
    {
        return [
            new ColumnaExportable('fecha_hora', 'Fecha y hora', TipoColumna::FechaHora),
            new ColumnaExportable('empresa_id', 'Empresa (id)', TipoColumna::Entero),
            new ColumnaExportable('autor_nombre', 'Autor'),
            new ColumnaExportable('autor_usuario', 'Usuario'),
            new ColumnaExportable('autor_rol', 'Rol'),
            new ColumnaExportable('es_superadmin', 'Superadmin', TipoColumna::Booleano),
            new ColumnaExportable('modulo', 'Módulo'),
            new ColumnaExportable('accion', 'Acción'),
            new ColumnaExportable('descripcion', 'Descripción'),
            new ColumnaExportable('entidad', 'Entidad'),
            new ColumnaExportable('entidad_id', 'Entidad (id)', TipoColumna::Entero),
            new ColumnaExportable('motivo', 'Motivo'),
            new ColumnaExportable('valores_anteriores', 'Valores anteriores'),
            new ColumnaExportable('valores_nuevos', 'Valores nuevos'),
            new ColumnaExportable('ip', 'IP'),
            new ColumnaExportable('evento_id', 'Id de evento'),
        ];
    }

    public function filas(): iterable
    {
        foreach ($this->repositorio()->filasVisibles($this->visibilidad, $this->filtros, $this->hastaId) as $fila) {
            /** @var BitacoraAuditoria $fila */
            yield [
                'fecha_hora' => $fila->fecha_hora,
                'empresa_id' => $fila->empresa_id,
                'autor_nombre' => $fila->autor_nombre,
                'autor_usuario' => $fila->autor_usuario,
                'autor_rol' => $fila->autor_rol,
                'es_superadmin' => $fila->es_superadmin,
                'modulo' => $fila->modulo,
                'accion' => $fila->accion->value,
                'descripcion' => $fila->descripcion,
                'entidad' => $fila->entidad,
                'entidad_id' => $fila->entidad_id,
                'motivo' => $fila->motivo,
                'valores_anteriores' => $fila->valores_anteriores,
                'valores_nuevos' => $fila->valores_nuevos,
                'ip' => $fila->ip,
                'evento_id' => $fila->evento_id,
            ];
        }
    }

    public function totalFilas(): int
    {
        return $this->repositorio()->contarVisibles($this->visibilidad, $this->filtros, $this->hastaId);
    }

    public function filtrosAplicados(): array
    {
        return $this->filtros->aArray();
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return app(AccesoAuditoria::class)->puede($usuario, 'exportar');
    }

    private function repositorio(): BitacoraAuditoriaRepository
    {
        return app(BitacoraAuditoriaRepository::class);
    }
}
