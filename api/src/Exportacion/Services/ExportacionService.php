<?php

namespace App\Exportacion\Services;

use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Exportacion\Constants\ExportacionConstants;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ParametrosExportacion;
use App\Exportacion\Enums\EstadoExportacion;
use App\Exportacion\Enums\FormatoExportacion;
use App\Exportacion\Jobs\ProcesarExportacionJob;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Repositories\ExportacionRepository;
use App\Notificaciones\Enums\TipoNotificacion;
use App\Notificaciones\Services\NotificacionService;
use App\Usuarios\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Exportación asíncrona y genérica: no conoce ningún reporte, solo `FuenteExportable`.
 * `solicitar` valida, registra y encola (el controlador responde 202); el worker ejecuta `procesar`,
 * que escribe el archivo en streaming en el volumen y avisa al solicitante por notificación.
 */
class ExportacionService
{
    public function __construct(
        protected ExportacionRepository $exportaciones,
        protected EmisorAuditoria $auditoria,
        protected NotificacionService $notificaciones,
    ) {}

    public function solicitar(FuenteExportable $fuente, FormatoExportacion $formato, Usuario $solicitante): Exportacion
    {
        if (! $fuente->autorizadoPara($solicitante)) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        $filas = $fuente->totalFilas();

        if ($filas > ExportacionConstants::FILAS_MAXIMAS) {
            throw ValidationException::withMessages([
                'formato' => __('validation.custom.exportacion.excede_limite', [
                    'filas' => number_format($filas, 0, ',', '.'),
                    'maximo' => number_format(ExportacionConstants::FILAS_MAXIMAS, 0, ',', '.'),
                ]),
            ]);
        }

        /** @var Exportacion $exportacion */
        $exportacion = DB::transaction(fn () => $this->exportaciones->crear([
            'empresa_id' => $solicitante->empresa_id,
            'usuario_id' => $solicitante->id,
            'reporte' => $fuente->reporte(),
            'formato' => $formato,
            'estado' => EstadoExportacion::Pendiente,
            'filtros' => $fuente->filtrosAplicados() ?: null,
            'filas' => $filas,
        ]));

        $this->auditoria->registrar(
            AccionAuditoria::Exportar,
            $fuente->modulo(),
            "Exportación solicitada: {$fuente->titulo()} ({$formato->value}, {$filas} filas)",
            'exportacion',
            $exportacion->id,
            nuevos: ['reporte' => $fuente->reporte(), 'formato' => $formato->value, 'filtros' => $fuente->filtrosAplicados(), 'filas' => $filas],
            autor: $solicitante,
        );

        ProcesarExportacionJob::dispatch($exportacion->id, $exportacion->empresa_id, $fuente, $formato);

        return $exportacion;
    }

    /** Corre en el worker, sin sesión: todo el alcance sale del `empresa_id` explícito del job. */
    public function procesar(int $id, ?int $empresaId, FuenteExportable $fuente, FormatoExportacion $formato): void
    {
        $exportacion = $this->exportaciones->buscarParaProcesar($id, $empresaId);
        $disco = Storage::disk(ExportacionConstants::DISCO);
        $ruta = now()->format('Y/m').'/'.Str::uuid().'.'.$formato->extension();

        $this->exportaciones->actualizar($exportacion, ['estado' => EstadoExportacion::Procesando, 'iniciada_en' => now()]);

        try {
            $disco->makeDirectory(dirname($ruta));

            $filas = $this->escribirArchivo($fuente, $formato, $exportacion, $disco->path($ruta));

            $nombre = Str::slug($fuente->nombreArchivo()).'-'.now()->format('Ymd-His').'.'.$formato->extension();

            $this->exportaciones->actualizar($exportacion, [
                'estado' => EstadoExportacion::Completada,
                'filas' => $filas,
                'nombre_archivo' => $nombre,
                'ruta_archivo' => $ruta,
                'tamano_bytes' => $disco->size($ruta),
                'completada_en' => now(),
            ]);

            $this->notificaciones->notificar(
                $exportacion->usuario_id,
                $exportacion->empresa_id,
                TipoNotificacion::ExportacionLista,
                'Exportación lista',
                "{$fuente->titulo()} ({$formato->value}, ".number_format($filas, 0, ',', '.').' filas) está lista para descargar.',
                ['exportacion_id' => $exportacion->id, 'formato' => $formato->value],
            );
        } catch (Throwable $excepcion) {
            $disco->delete($ruta);
            report($excepcion);

            $this->marcarFallida($id, $empresaId, $fuente->titulo(), $excepcion);
        }
    }

    /** Idempotente: la llama `procesar` ante un error y el job ante un fallo no capturado (p. ej. timeout). */
    public function marcarFallida(int $id, ?int $empresaId, string $titulo, Throwable $excepcion): void
    {
        $exportacion = $this->exportaciones->buscarParaProcesar($id, $empresaId);

        if (in_array($exportacion->estado, [EstadoExportacion::Fallida, EstadoExportacion::Completada], true)) {
            return;
        }

        $this->exportaciones->actualizar($exportacion, [
            'estado' => EstadoExportacion::Fallida,
            'error' => mb_substr($excepcion->getMessage(), 0, 500),
            'completada_en' => now(),
        ]);

        $this->notificaciones->notificar(
            $exportacion->usuario_id,
            $exportacion->empresa_id,
            TipoNotificacion::ExportacionFallida,
            'Exportación fallida',
            "No se pudo generar «{$titulo}». Intenta de nuevo o contacta al administrador.",
            ['exportacion_id' => $exportacion->id],
        );
    }

    public function listar(Usuario $usuario, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->exportaciones->paginarDelUsuario($usuario->id, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    /** Solo el solicitante descarga su archivo; cualquier otro recibe 404. La descarga también queda auditada. */
    public function descargar(int $id, Usuario $usuario): StreamedResponse
    {
        $exportacion = $this->exportaciones->buscarDelUsuarioOFallar($id, $usuario->id);
        $disco = Storage::disk(ExportacionConstants::DISCO);

        if ($exportacion->estado !== EstadoExportacion::Completada || ! $exportacion->ruta_archivo || ! $disco->exists($exportacion->ruta_archivo)) {
            abort(404, 'El archivo no está disponible.');
        }

        $this->auditoria->registrar(
            AccionAuditoria::Exportar,
            Str::before($exportacion->reporte, '.'),
            "Descarga de exportación: {$exportacion->nombre_archivo}",
            'exportacion',
            $exportacion->id,
            nuevos: ['reporte' => $exportacion->reporte, 'formato' => $exportacion->formato->value, 'filtros' => $exportacion->filtros, 'filas' => $exportacion->filas],
            autor: $usuario,
        );

        return $disco->download($exportacion->ruta_archivo, $exportacion->nombre_archivo, ['Content-Type' => $exportacion->formato->tipoMime()]);
    }

    private function escribirArchivo(FuenteExportable $fuente, FormatoExportacion $formato, Exportacion $exportacion, string $destino): int
    {
        $escritor = app($formato->escritor());
        $escritor->abrir($destino, new ParametrosExportacion(
            titulo: $fuente->titulo(),
            empresa: $exportacion->empresa?->nombre_empresa ?? 'Todas las empresas (plataforma)',
            generadoPor: $exportacion->usuario?->nombre ?? 'Desconocido',
            generadoEn: CarbonImmutable::now(),
            filas: $exportacion->filas,
            filtros: $fuente->filtrosAplicados(),
        ), $fuente->columnas());

        $filas = 0;

        foreach ($fuente->filas() as $fila) {
            if (++$filas > ExportacionConstants::FILAS_MAXIMAS) {
                throw new RuntimeException('La exportación superó el máximo de filas permitido.');
            }

            $escritor->escribirFila($fila);
        }

        $escritor->cerrar();

        return $filas;
    }
}
