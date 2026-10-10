<?php

namespace App\Conciliacion\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Conciliacion\Constants\ConciliacionConstants;
use App\Conciliacion\Enums\EstadoExtracto;
use App\Conciliacion\Jobs\ValidarExtractoBancarioJob;
use App\Conciliacion\Lectura\Data\ExtractoLeido;
use App\Conciliacion\Lectura\FormatoExtractoInvalido;
use App\Conciliacion\Lectura\LectorExtractoBancolombia;
use App\Conciliacion\Models\CuentaBancaria;
use App\Conciliacion\Models\ExtractoBancario;
use App\Conciliacion\Repositories\ExtractoBancarioRepository;
use App\Conciliacion\Repositories\MovimientoExtractoRepository;
use App\Core\Dinero\Dinero;
use App\Notificaciones\Enums\TipoNotificacion;
use App\Notificaciones\Services\NotificacionService;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validar (formato, cuadre de saldos, solape de fechas) es rápido y se hace de una vez en la petición, con
 * mensajes 422 precisos; solo la escritura de los movimientos (hasta varios cientos de filas) y la bitácora
 * quedan para el job, siguiendo el mismo patrón 202+cola que Exportación/Importación.
 */
class ExtractoBancarioService
{
    public function __construct(
        protected ExtractoBancarioRepository $extractos,
        protected MovimientoExtractoRepository $movimientos,
        protected EmisorAuditoria $auditoria,
        protected NotificacionService $notificaciones,
    ) {}

    public function listar(CuentaBancaria $cuentaBancaria): Collection
    {
        return $this->extractos->paraCuenta($cuentaBancaria->id);
    }

    /** @param  string  $mesPrimeraFilaYm  "YYYY-MM" del primer movimiento del archivo: sus fechas no traen año. */
    public function solicitarCarga(Usuario $usuario, CuentaBancaria $cuentaBancaria, UploadedFile $archivo, string $mesPrimeraFilaYm): ExtractoBancario
    {
        $numeroCuentaArchivo = $this->extraerNumeroCuentaDelNombre($archivo);

        if ($numeroCuentaArchivo !== null && $numeroCuentaArchivo !== $cuentaBancaria->numero_cuenta) {
            throw ValidationException::withMessages(['archivo' => __('validation.custom.extracto_bancario.cuenta_no_coincide')]);
        }

        [$anio, $mes] = array_map('intval', explode('-', $mesPrimeraFilaYm));

        try {
            $leido = (new LectorExtractoBancolombia($archivo->getRealPath(), $anio, $mes))->leer();
        } catch (FormatoExtractoInvalido) {
            throw ValidationException::withMessages(['archivo' => __('validation.custom.extracto_bancario.formato_invalido')]);
        }

        if (! $this->cuadra($leido)) {
            throw ValidationException::withMessages(['archivo' => __('validation.custom.extracto_bancario.saldos_no_cuadran')]);
        }

        [$fechaDesde, $fechaHasta] = $this->rangoDeFechas($leido, $anio, $mes);

        if ($this->extractos->existeVigenteEnRango($cuentaBancaria->id, $fechaDesde, $fechaHasta)) {
            throw ValidationException::withMessages(['archivo' => __('validation.custom.extracto_bancario.periodo_ya_cargado')]);
        }

        $ruta = Storage::disk(ConciliacionConstants::DISCO)->putFileAs(now()->format('Y/m'), $archivo, Str::uuid().'.xlsx');

        $extracto = $this->extractos->crear([
            'cuenta_bancaria_id' => $cuentaBancaria->id,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'saldo_anterior' => $leido->resumen->saldoAnterior,
            'total_abonos' => $leido->resumen->totalAbonos,
            'total_cargos' => $leido->resumen->totalCargos,
            'saldo_actual' => $leido->resumen->saldoActual,
            'archivo_ruta' => $ruta,
            'estado' => EstadoExtracto::Cargando,
            'cargado_por' => $usuario->id,
        ]);

        ValidarExtractoBancarioJob::dispatch($extracto->id, $usuario->empresa_id);

        return $extracto;
    }

    /**
     * Sin sesión: lo llama el job. Recibe `empresaId` explícito (ver tenant-isolation). El archivo ya se validó
     * al cargarlo; esto solo repite la lectura (barata, un archivo de cientos de filas) para escribir los
     * movimientos — si algo cambió entre medias (el archivo ya no existe, etc.) se rechaza igual que entonces.
     */
    public function procesar(int $extractoId, int $empresaId): void
    {
        $extracto = $this->extractos->buscarPorIdYEmpresa($extractoId, $empresaId);

        try {
            $leido = (new LectorExtractoBancolombia(
                Storage::disk(ConciliacionConstants::DISCO)->path($extracto->archivo_ruta),
                $extracto->fecha_desde->year,
                $extracto->fecha_desde->month,
            ))->leer();
        } catch (FormatoExtractoInvalido) {
            $this->rechazar($extracto, __('validation.custom.extracto_bancario.formato_invalido'));

            return;
        }

        if (! $this->cuadra($leido)) {
            $this->rechazar($extracto, __('validation.custom.extracto_bancario.saldos_no_cuadran'));

            return;
        }

        DB::transaction(function () use ($extracto, $leido, $empresaId) {
            $this->extractos->actualizar($extracto, ['estado' => EstadoExtracto::Cargado]);

            $this->movimientos->insertarVarios($empresaId, $extracto->id, $leido->movimientos);
        });

        $rango = "{$extracto->fecha_desde->format('Y-m-d')} a {$extracto->fecha_hasta->format('Y-m-d')}";

        $this->auditoria->registrarCreacion(
            AuditoriaConstants::MODULO_CONCILIACION,
            "Extracto bancario de {$extracto->cuentaBancaria->numero_cuenta} ({$rango}) cargado (".count($leido->movimientos).' movimientos)',
            $extracto,
        );

        $this->notificar($extracto, TipoNotificacion::ExtractoBancarioCargado, 'Extracto cargado', "El extracto {$rango} quedó cargado y listo para conciliar.");
    }

    public function marcarFallida(int $extractoId, int $empresaId): void
    {
        $this->rechazar($this->extractos->buscarPorIdYEmpresa($extractoId, $empresaId), __('validation.custom.extracto_bancario.error_inesperado'));
    }

    private function rechazar(ExtractoBancario $extracto, string $motivo): void
    {
        $this->extractos->actualizar($extracto, ['estado' => EstadoExtracto::Rechazado, 'motivo_rechazo' => $motivo]);

        $this->notificar($extracto, TipoNotificacion::ExtractoBancarioRechazado, 'Extracto rechazado', $motivo);
    }

    private function notificar(ExtractoBancario $extracto, TipoNotificacion $tipo, string $titulo, string $mensaje): void
    {
        $this->notificaciones->notificar(
            $extracto->cargado_por,
            $extracto->empresa_id,
            $tipo,
            $titulo,
            $mensaje,
            ['extracto_bancario_id' => $extracto->id],
        );
    }

    /** Saldo anterior + movimientos debe llegar al saldo actual, y el saldo corrido de la última fila también. */
    private function cuadra(ExtractoLeido $leido): bool
    {
        $movimientos = $leido->movimientos;
        $suma = Dinero::sumarVarios(...array_map(fn ($movimiento) => $movimiento->valor, $movimientos));
        $saldoCalculado = $leido->resumen->saldoAnterior->sumar($suma);
        $ultimoSaldo = $movimientos === [] ? $leido->resumen->saldoAnterior : end($movimientos)->saldo;

        return $saldoCalculado->igualA($leido->resumen->saldoActual) && $ultimoSaldo->igualA($leido->resumen->saldoActual);
    }

    /** @return array{0: string, 1: string} fecha_desde y fecha_hasta ("Y-m-d"), de los movimientos o del mes ancla si el archivo no trae ninguno. */
    private function rangoDeFechas(ExtractoLeido $leido, int $anioAncla, int $mesAncla): array
    {
        $movimientos = $leido->movimientos;

        if ($movimientos === []) {
            $inicioMes = sprintf('%04d-%02d-01', $anioAncla, $mesAncla);

            return [$inicioMes, $inicioMes];
        }

        return [$movimientos[0]->fecha, end($movimientos)->fecha];
    }

    /**
     * El nombre trae el número de cuenta como primer segmento (`{cuenta}_...`), pero el resto del nombre no es
     * de fiar como período: un extracto real de Bancolombia trajo `..._202609_...` con movimientos de julio, el
     * segundo segmento no es el mes del archivo. Solo se usa como pista opcional: si no calza con ese patrón,
     * no se rechaza nada por eso.
     */
    private function extraerNumeroCuentaDelNombre(UploadedFile $archivo): ?string
    {
        $coincide = preg_match('/^(\d+)_/', $archivo->getClientOriginalName(), $coincidencia);

        return $coincide === 1 ? $coincidencia[1] : null;
    }
}
