<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Clientes\Repositories\ClienteRepository;
use App\Contabilidad\Models\MigracionApertura;
use App\Contabilidad\Repositories\MigracionAperturaRepository;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use App\Contabilidad\Repositories\SaldoAperturaRepository;
use App\Proveedores\Repositories\ProveedorRepository;
use App\Terceros\Enums\TipoTercero;
use App\Terceros\Models\Tercero;
use App\Terceros\Repositories\OtroTerceroRepository;
use App\Terceros\Repositories\TerceroRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Puerto directo de `modules/contabilidad/migracion.php` del legado: importa CSV de
 * plan de cuentas, terceros o saldos de apertura, aceptando/rechazando fila por fila
 * dentro de una única transacción, y deja un reporte auditable en `migraciones_apertura`.
 */
class MigracionAperturaService
{
    public function __construct(
        protected MigracionAperturaRepository $migraciones,
        protected PlanCuentaRepository $cuentas,
        protected SaldoAperturaRepository $saldos,
        protected TerceroRepository $terceros,
        protected OtroTerceroRepository $otrosTerceros,
        protected ClienteRepository $clientes,
        protected ProveedorRepository $proveedores,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->migraciones->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function importar(string $tipo, UploadedFile $archivo): MigracionApertura
    {
        $filas = array_map('str_getcsv', file($archivo->getRealPath()));
        $encabezado = array_map('trim', array_shift($filas));
        $aceptados = 0;
        $rechazados = 0;
        $errores = [];

        DB::transaction(function () use ($tipo, $filas, $encabezado, &$aceptados, &$rechazados, &$errores) {
            $datosPorFila = [];

            foreach ($filas as $indice => $fila) {
                if (count($fila) < count($encabezado)) {
                    $rechazados++;
                    $errores[] = 'Fila '.($indice + 2).': columnas incompletas';

                    continue;
                }

                $datosPorFila[$indice] = array_combine($encabezado, $fila);
            }

            $precarga = $this->precargarParaTipo($tipo, $datosPorFila);

            foreach ($datosPorFila as $indice => $datos) {
                try {
                    $this->importarFila($tipo, $datos, $precarga);
                    $aceptados++;
                } catch (Throwable $e) {
                    $rechazados++;
                    $errores[] = 'Fila '.($indice + 2).': '.$e->getMessage();
                }
            }
        });

        $migracion = $this->migraciones->crear([
            'tipo' => $tipo,
            'archivo_original' => $archivo->getClientOriginalName(),
            'registros_aceptados' => $aceptados,
            'registros_rechazados' => $rechazados,
            'detalle_errores' => $errores,
            'usuario_id' => Auth::id(),
        ]);

        $this->auditoria->registrar(
            AccionAuditoria::Importar,
            AuditoriaConstants::MODULO_CONTABILIDAD,
            "Migración de apertura ({$tipo}) importada desde {$archivo->getClientOriginalName()}: {$aceptados} aceptados, {$rechazados} rechazados",
            'migracion_apertura',
            $migracion->id,
            nuevos: [
                'tipo' => $tipo,
                'archivo_original' => $archivo->getClientOriginalName(),
                'registros_aceptados' => $aceptados,
                'registros_rechazados' => $rechazados,
                'primeros_errores' => array_slice($errores, 0, 20),
            ],
        );

        return $migracion;
    }

    /**
     * Precarga de una sola vez (en pocas consultas, no una por fila) lo que `importarFila()`
     * necesita para decidir cada fila: las cuentas referenciadas por código (`saldos_iniciales`)
     * o los terceros ya existentes por documento, con sus extensiones cargadas (`terceros`).
     * `plan_cuentas` no tiene nada que precargar: cada fila es un alta independiente.
     */
    private function precargarParaTipo(string $tipo, array $datosPorFila): array
    {
        if ($tipo === 'saldos_iniciales') {
            $codigos = array_unique(array_column($datosPorFila, 'codigo_cuenta'));

            return ['cuentas_por_codigo' => $this->cuentas->porCodigos($codigos)->keyBy('codigo')];
        }

        if ($tipo === 'terceros') {
            $pares = array_map(fn (array $datos) => [
                'tipo_documento' => $datos['tipo_documento'] ?? '',
                'numero_documento' => $datos['numero_documento'] ?? '',
            ], $datosPorFila);

            $terceros = $this->terceros->porDocumentos($pares)->keyBy(
                fn (Tercero $tercero) => $this->claveDocumento($tercero->tipo_documento->value, $tercero->numero_documento),
            );

            return ['terceros_por_documento' => $terceros];
        }

        return [];
    }

    private function claveDocumento(string $tipoDocumento, string $numeroDocumento): string
    {
        return $tipoDocumento.'|'.$numeroDocumento;
    }

    private function importarFila(string $tipo, array $datos, array &$precarga): void
    {
        if ($tipo === 'plan_cuentas') {
            $this->cuentas->crear([
                'codigo' => $datos['codigo'],
                'nombre' => $datos['nombre'],
                'naturaleza' => $datos['naturaleza'],
                'nivel' => $datos['nivel'] ?? 1,
                'tipo' => $datos['tipo'],
                'permite_movimiento' => true,
            ]);

            return;
        }

        if ($tipo === 'terceros') {
            $tipoTercero = TipoTercero::tryFrom($datos['tipo'] ?? '');
            if (! $tipoTercero) {
                throw new RuntimeException('Tipo de tercero inválido: "'.($datos['tipo'] ?? '').'" (debe ser cliente, proveedor u otro).');
            }

            $clave = $this->claveDocumento($datos['tipo_documento'], $datos['numero_documento']);
            $tercero = $precarga['terceros_por_documento'][$clave] ?? null;

            if (! $tercero) {
                $tercero = $this->terceros->crear([
                    'tipo_documento' => $datos['tipo_documento'],
                    'numero_documento' => $datos['numero_documento'],
                    'nombre' => $datos['nombre'],
                ]);
                // Recién creado: no puede tener extensión todavía — evita un lazy-load por fila.
                $tercero->setRelation('cliente', null);
                $tercero->setRelation('proveedor', null);
                $tercero->setRelation('otroTercero', null);
                $precarga['terceros_por_documento'][$clave] = $tercero;
            }

            $yaRegistradoComo = match ($tipoTercero) {
                TipoTercero::Cliente => $tercero->cliente !== null,
                TipoTercero::Proveedor => $tercero->proveedor !== null,
                TipoTercero::Otro => $tercero->otroTercero !== null,
            };

            if ($yaRegistradoComo) {
                throw new RuntimeException('El tercero '.$datos['numero_documento'].' ya está registrado como '.$tipoTercero->value.'.');
            }

            $nuevaExtension = match ($tipoTercero) {
                TipoTercero::Cliente => $this->clientes->crear(['tercero_id' => $tercero->id, 'activo' => true]),
                TipoTercero::Proveedor => $this->proveedores->crear(['tercero_id' => $tercero->id, 'activo' => true]),
                TipoTercero::Otro => $this->otrosTerceros->crear(['tercero_id' => $tercero->id, 'activo' => true]),
            };

            // Deja la extensión recién creada en la copia precargada, por si el mismo documento
            // vuelve a aparecer más adelante en el mismo archivo (debe rechazarse como duplicado).
            $tercero->setRelation(match ($tipoTercero) {
                TipoTercero::Cliente => 'cliente',
                TipoTercero::Proveedor => 'proveedor',
                TipoTercero::Otro => 'otroTercero',
            }, $nuevaExtension);

            return;
        }

        if ($tipo === 'saldos_iniciales') {
            $cuenta = $precarga['cuentas_por_codigo'][$datos['codigo_cuenta']] ?? null;
            if (! $cuenta) {
                throw new RuntimeException('Cuenta no encontrada: '.$datos['codigo_cuenta']);
            }

            $this->saldos->crear([
                'cuenta_id' => $cuenta->id,
                'fecha_corte' => $datos['fecha_corte'],
                'debito' => $datos['debito'] ?? 0,
                'credito' => $datos['credito'] ?? 0,
            ]);
        }
    }
}
