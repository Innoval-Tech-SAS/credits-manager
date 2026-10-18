<?php

namespace App\Importacion\Services;

use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Services\ComprobanteService;
use App\Importacion\Constants\ImportacionConstants;
use App\Importacion\Data\ContextoEjecucion;
use App\Importacion\Data\EmpleadorPendiente;
use App\Importacion\Data\FilaRechazada;
use App\Importacion\Definiciones\DefinicionImportacion;
use App\Importacion\Enums\EstadoImportacion;
use App\Importacion\Enums\FaseImportacion;
use App\Importacion\Enums\ImportacionSeveridad;
use App\Importacion\Enums\TipoImportacion;
use App\Importacion\Lectura\LectorCsv;
use App\Importacion\Models\Importacion;
use App\Importacion\Procesadores\ProcesadorFila;
use App\Importacion\Procesadores\ProcesadorFilaFactory;
use App\Importacion\Repositories\DatosImportacionRepository;
use App\Importacion\Repositories\ImportacionErrorRepository;
use App\Importacion\Repositories\ImportacionRepository;
use App\Notificaciones\Enums\TipoNotificacion;
use App\Notificaciones\Services\NotificacionService;
use App\Usuarios\Models\Usuario;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Corre en el worker, sin sesión: todo sale del `empresa_id` explícito. `validar` no escribe nada; `escribir` aplica
 * lo aceptado, fila por fila, cada una en su transacción. Las filas rechazadas en validación no se vuelven a evaluar.
 */
class ImportacionEjecutorService
{
    private const LOTE_ERRORES = 500;

    public function __construct(
        protected ImportacionRepository $importaciones,
        protected ImportacionErrorRepository $errores,
        protected DatosImportacionRepository $datos,
        protected ProcesadorFilaFactory $procesadores,
        protected ComprobanteService $comprobantes,
        protected NotificacionService $notificaciones,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function validar(int $id, int $empresaId): void
    {
        $importacion = $this->importaciones->buscarDeEmpresaOFallar($id, $empresaId);

        if ($importacion->estado !== EstadoImportacion::Pendiente) {
            return;
        }

        $this->conAutor($importacion, function (Usuario $usuario) use ($importacion) {
            $this->importaciones->actualizar($importacion, ['estado' => EstadoImportacion::Validando, 'iniciada_en' => now()]);

            try {
                $ctx = new ContextoEjecucion($importacion->empresa_id, $usuario, false, null, false);
                $conteo = $this->recorrer($importacion, $ctx);

                $this->importaciones->actualizar($importacion, [
                    'estado' => EstadoImportacion::PendienteDecision,
                    'validada_en' => now(),
                    'total_filas' => $conteo['total'],
                    'filas_aceptadas' => $conteo['aceptadas'],
                    'filas_rechazadas' => $conteo['rechazadas'],
                    'filas_pendientes' => $conteo['pendientes'],
                    'empleadores_pendientes' => count($ctx->empleadoresFaltantes),
                ]);

                $this->notificar(
                    $importacion,
                    TipoNotificacion::ImportacionValidada,
                    'Importación validada',
                    "{$importacion->archivo_original}: {$conteo['aceptadas']} aceptadas, {$conteo['rechazadas']} rechazadas"
                        .($conteo['pendientes'] > 0 ? " y {$conteo['pendientes']} esperan tu decisión." : '.'),
                );
            } catch (Throwable $excepcion) {
                $this->fallar($importacion, $excepcion);
            }
        });
    }

    public function escribir(int $id, int $empresaId): void
    {
        $importacion = $this->importaciones->buscarDeEmpresaOFallar($id, $empresaId);

        if ($importacion->estado !== EstadoImportacion::Importando) {
            return;
        }

        $this->conAutor($importacion, function (Usuario $usuario) use ($importacion) {
            try {
                $contabilizar = $this->puedeContabilizar($importacion->tipo);
                $generarAsiento = $contabilizar
                    && $importacion->decision_generar_asiento === true
                    && $usuario->tienePermiso(...ImportacionConstants::PERMISO_CONTABILIZAR);

                if ($generarAsiento && $importacion->tipo === TipoImportacion::Creditos && $this->datos->empresaTieneSaldosApertura($importacion->empresa_id)) {
                    $generarAsiento = false;
                    $this->errores->insertarLote([$this->error($importacion, null, null, ImportacionSeveridad::Aviso, 'No se generaron los asientos de desembolso: la empresa ya tiene saldos iniciales y la cartera duplicaría.', FaseImportacion::Escritura)]);
                }

                $ctx = new ContextoEjecucion(
                    $importacion->empresa_id,
                    $usuario,
                    true,
                    $importacion->decision_crear_empleadores === true,
                    $generarAsiento,
                );

                $conteo = $this->recorrer($importacion, $ctx);

                $this->importaciones->actualizar($importacion, [
                    'estado' => EstadoImportacion::Completada,
                    'filas_aceptadas' => $conteo['aceptadas'],
                    'filas_rechazadas' => $importacion->filas_rechazadas + $conteo['rechazadas'],
                    'filas_pendientes' => 0,
                    'completada_en' => now(),
                ]);

                $this->auditoria->registrar(
                    AccionAuditoria::Importar,
                    'importaciones',
                    "Importación completada: {$importacion->archivo_original} ({$conteo['aceptadas']} escritas, {$importacion->filas_rechazadas} y {$conteo['rechazadas']} rechazadas)",
                    'importacion',
                    $importacion->id,
                    nuevos: ['tipo' => $importacion->tipo->value, 'aceptadas' => $conteo['aceptadas']],
                    autor: $usuario,
                );

                $this->notificar(
                    $importacion,
                    TipoNotificacion::ImportacionCompletada,
                    'Importación completada',
                    "{$importacion->archivo_original}: ".($importacion->filas_rechazadas + $conteo['rechazadas'] > 0
                        ? 'revisa el reporte de errores.'
                        : 'todas las filas se importaron.'),
                );
            } catch (Throwable $excepcion) {
                $this->fallar($importacion, $excepcion);
            }
        });
    }

    public function puedeContabilizar(TipoImportacion $tipo): bool
    {
        return match ($tipo) {
            TipoImportacion::Creditos => $this->comprobantes->eventosContablesActivos(EventoContable::DesembolsoCredito),
            TipoImportacion::Pagos => $this->comprobantes->eventosContablesActivos(EventoContable::RecaudoCapital, EventoContable::RecaudoInteres),
            default => false,
        };
    }

    /**
     * Recorre el archivo en lotes: cada lote precarga sus referencias en pocas consultas y luego procesa fila a fila.
     *
     * @return array{total: int, aceptadas: int, rechazadas: int, pendientes: int}
     */
    private function recorrer(Importacion $importacion, ContextoEjecucion $ctx): array
    {
        $procesador = $this->procesadores->para($importacion->tipo);
        $ctx->empleadoresPorNombre = $this->datos->empleadoresPorNombre($importacion->empresa_id);

        $saltar = $ctx->escribir ? $this->errores->filasRechazadasEnValidacion($importacion->id) : [];
        $lector = new LectorCsv(Storage::disk(ImportacionConstants::DISCO)->path($importacion->archivo_ruta), $importacion->tipo);
        $tamanoLote = (int) config('importaciones.tamano_lote', 500);

        $conteo = ['total' => 0, 'aceptadas' => 0, 'rechazadas' => 0, 'pendientes' => 0];
        $pendientesDeErrores = [];
        $lote = [];

        foreach ($lector->filas() as $registro) {
            $conteo['total']++;

            if (isset($saltar[$registro['fila']])) {
                continue;
            }

            $lote[] = $registro;

            if (count($lote) >= $tamanoLote) {
                $this->procesarLote($lote, $procesador, $ctx, $importacion, $conteo, $pendientesDeErrores);
                $lote = [];
            }
        }

        if ($lote !== []) {
            $this->procesarLote($lote, $procesador, $ctx, $importacion, $conteo, $pendientesDeErrores);
        }

        $this->volcarErrores($pendientesDeErrores);

        return $conteo;
    }

    /**
     * @param  list<array{fila: int, valores: array<string, string>}>  $lote
     * @param  array{total: int, aceptadas: int, rechazadas: int, pendientes: int}  $conteo
     * @param  list<array<string, mixed>>  $pendientesDeErrores
     */
    private function procesarLote(array $lote, ProcesadorFila $procesador, ContextoEjecucion $ctx, Importacion $importacion, array &$conteo, array &$pendientesDeErrores): void
    {
        $procesador->precargar(array_column($lote, 'valores'), $ctx);
        $fase = $ctx->escribir ? FaseImportacion::Escritura : FaseImportacion::Validacion;

        foreach ($lote as $registro) {
            $fila = $registro['fila'];
            $ctx->reportes = [];

            try {
                if (DefinicionImportacion::esFilaDeEjemplo($importacion->tipo, $registro['valores'])) {
                    throw new FilaRechazada('Esta fila es el ejemplo de la plantilla: bórrala antes de importar.');
                }

                if ($ctx->escribir) {
                    DB::transaction(fn () => $procesador->procesar($registro['valores'], $ctx));
                } else {
                    $procesador->procesar($registro['valores'], $ctx);
                }

                $conteo['aceptadas']++;
            } catch (EmpleadorPendiente) {
                $conteo['pendientes']++;
            } catch (FilaRechazada $excepcion) {
                $conteo['rechazadas']++;
                $pendientesDeErrores[] = $this->error($importacion, $fila, $excepcion->columna, ImportacionSeveridad::Error, $excepcion->getMessage(), $fase);
            } catch (ValidationException $excepcion) {
                $conteo['rechazadas']++;
                $columna = array_key_first($excepcion->errors());
                $pendientesDeErrores[] = $this->error($importacion, $fila, is_string($columna) ? $columna : null, ImportacionSeveridad::Error, $excepcion->validator->errors()->first(), $fase);
            } catch (Throwable $excepcion) {
                report($excepcion);
                $conteo['rechazadas']++;
                $pendientesDeErrores[] = $this->error($importacion, $fila, null, ImportacionSeveridad::Error, ImportacionConstants::MOTIVO_FILA_INESPERADA, $fase);
            }

            foreach ($ctx->reportes as $reporte) {
                $pendientesDeErrores[] = $this->error($importacion, $fila, $reporte['columna'], ImportacionSeveridad::from($reporte['severidad']), $reporte['motivo'], $fase);
            }

            if (count($pendientesDeErrores) >= self::LOTE_ERRORES) {
                $this->volcarErrores($pendientesDeErrores);
                $pendientesDeErrores = [];
            }
        }

        $this->volcarErrores($pendientesDeErrores);
    }

    /** @param list<array<string, mixed>> $filas */
    private function volcarErrores(array &$filas): void
    {
        if ($filas !== []) {
            $this->errores->insertarLote($filas);
            $filas = [];
        }
    }

    /** @return array<string, mixed> */
    private function error(Importacion $importacion, ?int $fila, ?string $clave, ImportacionSeveridad $severidad, string $motivo, FaseImportacion $fase): array
    {
        $etiquetas = array_column(DefinicionImportacion::para($importacion->tipo)['columnas'], 'encabezado', 'clave');

        return [
            'empresa_id' => $importacion->empresa_id,
            'importacion_id' => $importacion->id,
            'fila' => $fila,
            'columna' => $clave === null ? null : mb_substr($etiquetas[$clave] ?? $clave, 0, 100),
            'severidad' => $severidad->value,
            'fase' => $fase->value,
            'motivo' => mb_substr($motivo, 0, 500),
        ];
    }

    /** Lo llama el job ante un fallo no capturado (p. ej. timeout). */
    public function marcarFallida(int $id, int $empresaId, Throwable $excepcion): void
    {
        $importacion = $this->importaciones->buscarDeEmpresaOFallar($id, $empresaId);

        if (in_array($importacion->estado, [EstadoImportacion::Completada, EstadoImportacion::Fallida, EstadoImportacion::Cancelada], true)) {
            return;
        }

        $this->fallar($importacion, $excepcion);
    }

    private function fallar(Importacion $importacion, Throwable $excepcion): void
    {
        report($excepcion);

        $this->importaciones->actualizar($importacion, [
            'estado' => EstadoImportacion::Fallida,
            'error' => ImportacionConstants::MOTIVO_ERROR_INESPERADO,
            'completada_en' => now(),
        ]);

        $this->notificar($importacion, TipoNotificacion::ImportacionFallida, 'Importación fallida', "No se pudo procesar {$importacion->archivo_original}. Revisa el archivo e intenta de nuevo.");
    }

    private function notificar(Importacion $importacion, TipoNotificacion $tipo, string $titulo, string $mensaje): void
    {
        $this->notificaciones->notificar(
            $importacion->usuario_id,
            $importacion->empresa_id,
            $tipo,
            $titulo,
            $mensaje,
            ['importacion_id' => $importacion->id],
        );
    }

    /**
     * Los servicios de clientes y contabilidad registran al autor con `Auth::id()`: el worker lo fija solo durante el
     * trabajo de esta importación y lo limpia al terminar, porque el worker reutiliza el proceso entre trabajos.
     */
    private function conAutor(Importacion $importacion, Closure $trabajo): void
    {
        $usuario = Usuario::query()
            ->where('empresa_id', $importacion->empresa_id)
            ->findOrFail($importacion->usuario_id);

        $anterior = Auth::user();
        Auth::setUser($usuario);

        try {
            $trabajo($usuario);
        } finally {
            $anterior ? Auth::setUser($anterior) : Auth::forgetUser();
        }
    }
}
