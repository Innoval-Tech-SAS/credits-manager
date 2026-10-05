<?php

namespace App\Facturacion\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Core\Consecutivos\Services\ConsecutivoDocumentoService;
use App\Core\Dinero\Dinero;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Models\Credito;
use App\Creditos\Repositories\CreditoRepository;
use App\Empresas\Services\EmpresaService;
use App\Facturacion\Documentos\FacturaPdf;
use App\Facturacion\Enums\EstadoDocumentoFacturacion;
use App\Facturacion\Enums\TipoDocumentoFacturacion;
use App\Facturacion\Models\DocumentoFacturacion;
use App\Facturacion\Models\LineaDocumentoFacturacion;
use App\Facturacion\Repositories\DocumentoFacturacionRepository;
use App\Facturacion\Repositories\LineaDocumentoFacturacionRepository;
use App\Impresion\Data\PdfGenerado;
use App\Impresion\Services\GeneradorPdf;
use App\Usuarios\Models\Usuario;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Facturas y notas internas. Reglas que el Service garantiza (la base no puede expresarlas):
 * - un borrador se edita y se borra; un documento emitido no cambia;
 * - el número sale de la secuencia del tipo dentro de la misma transacción de la emisión (sin huecos);
 * - una nota crédito no acredita más que lo que queda de la línea original, contando las notas ya emitidas;
 * - la factura «anulada» es derivada: sus notas crédito cubren su total. No se guarda como estado.
 */
class DocumentoFacturacionService
{
    public function __construct(
        protected DocumentoFacturacionRepository $documentos,
        protected LineaDocumentoFacturacionRepository $lineas,
        protected CreditoRepository $creditos,
        protected ConsecutivoDocumentoService $consecutivos,
        protected EmpresaService $empresas,
        protected GeneradorPdf $generador,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(?TipoDocumentoFacturacion $tipo, ?EstadoDocumentoFacturacion $estado, ?int $clienteId, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->documentos->paginar($tipo, $estado, $clienteId, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    /** Factura en borrador desde un crédito desembolsado (o ya pagado): el cliente y el crédito salen de él. */
    public function crearBorrador(int $creditoId, ?string $fecha, array $lineas): DocumentoFacturacion
    {
        /** @var Credito $credito */
        $credito = $this->creditos->buscarPorIdOFallar($creditoId);

        if (! in_array($credito->estado, [EstadoCredito::Desembolsado, EstadoCredito::Pagado], true)) {
            throw ValidationException::withMessages(['credito_id' => __('validation.custom.facturacion.credito_no_desembolsado')]);
        }

        return DB::transaction(function () use ($credito, $fecha, $lineas) {
            $documento = $this->documentos->crear([
                'tipo' => TipoDocumentoFacturacion::Factura,
                'estado' => EstadoDocumentoFacturacion::Borrador,
                'fecha' => $fecha ?? now('America/Bogota')->toDateString(),
                'cliente_id' => $credito->cliente_id,
                'credito_id' => $credito->id,
            ]);

            $this->lineas->sincronizar($documento, $this->lineasDeEntrada($lineas));

            return $this->conLineas($documento->refresh());
        });
    }

    public function actualizarBorrador(DocumentoFacturacion $documento, ?string $fecha, array $lineas): DocumentoFacturacion
    {
        $this->exigirBorrador($documento);

        return DB::transaction(function () use ($documento, $fecha, $lineas) {
            $bloqueado = $this->documentos->bloquear($documento->id);
            $this->exigirBorrador($bloqueado);

            if ($fecha !== null) {
                $bloqueado->update(['fecha' => $fecha]);
            }

            $this->lineas->sincronizar($bloqueado, $this->lineasDeEntrada($lineas));

            return $this->conLineas($bloqueado);
        });
    }

    public function eliminarBorrador(DocumentoFacturacion $documento): void
    {
        $this->exigirBorrador($documento);

        DB::transaction(function () use ($documento) {
            $bloqueado = $this->documentos->bloquear($documento->id);
            $this->exigirBorrador($bloqueado);

            $this->lineas->sincronizar($bloqueado, []);
            $bloqueado->delete();
        });
    }

    /** Emite la factura: asigna el número de la secuencia de facturas y la deja inmutable. */
    public function emitirFactura(DocumentoFacturacion $documento, Usuario $usuario): DocumentoFacturacion
    {
        $this->exigirBorrador($documento);

        return DB::transaction(function () use ($documento, $usuario) {
            $bloqueado = $this->documentos->bloquear($documento->id);
            $this->exigirBorrador($bloqueado);

            if ($this->documentos->sumaLineas($bloqueado->id) <= 0) {
                throw ValidationException::withMessages(['lineas' => __('validation.custom.facturacion.sin_lineas')]);
            }

            $emitido = $this->emitir($bloqueado, $usuario);

            $this->auditoria->registrar(
                AccionAuditoria::Crear,
                AuditoriaConstants::MODULO_FACTURACION,
                "Factura {$emitido->numero} emitida",
                'documento_facturacion',
                $emitido->id,
                nuevos: ['numero' => $emitido->numero, 'credito_id' => $emitido->credito_id],
                autor: $usuario,
            );

            return $this->conLineas($emitido);
        });
    }

    /**
     * Nota crédito sobre una factura, o nota débito sobre una factura o una nota crédito.
     * Nota crédito: cada línea corrige una línea de la factura, con el concepto de esa línea y sin pasarse de lo que queda.
     * Nota débito: líneas libres (concepto, descripción y valor), opcionalmente vinculadas a una línea de origen.
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    public function emitirNota(DocumentoFacturacion $origen, TipoDocumentoFacturacion $tipo, string $motivo, array $lineas, Usuario $usuario): DocumentoFacturacion
    {
        $this->exigirEmitido($origen);

        $tipoOrigenValido = $tipo === TipoDocumentoFacturacion::NotaCredito
            ? $origen->tipo === TipoDocumentoFacturacion::Factura
            : in_array($origen->tipo, [TipoDocumentoFacturacion::Factura, TipoDocumentoFacturacion::NotaCredito], true);

        if (! $tipoOrigenValido) {
            throw ValidationException::withMessages(['documento' => __('validation.custom.facturacion.origen_tipo_invalido')]);
        }

        return DB::transaction(function () use ($origen, $tipo, $motivo, $lineas, $usuario) {
            // Bloquear el origen: dos notas simultáneas sobre la misma factura se serializan y no acreditan dos veces el mismo saldo.
            $bloqueado = $this->documentos->bloquear($origen->id);
            $this->exigirEmitido($bloqueado);

            $lineasNota = $tipo === TipoDocumentoFacturacion::NotaCredito
                ? $this->lineasDeNotaCredito($bloqueado, $lineas)
                : $this->lineasDeNotaDebito($bloqueado, $lineas);

            $nota = $this->documentos->crear([
                'tipo' => $tipo,
                'estado' => EstadoDocumentoFacturacion::Borrador,
                'fecha' => now('America/Bogota')->toDateString(),
                'cliente_id' => $bloqueado->cliente_id,
                'credito_id' => $bloqueado->credito_id,
                'documento_origen_id' => $bloqueado->id,
                'motivo' => $motivo,
            ]);

            $this->lineas->crearParaDocumento($nota, $lineasNota);

            $emitida = $this->emitir($nota, $usuario);

            $this->auditoria->registrar(
                $tipo === TipoDocumentoFacturacion::NotaCredito ? AccionAuditoria::Anular : AccionAuditoria::Crear,
                AuditoriaConstants::MODULO_FACTURACION,
                "{$tipo->etiqueta()} {$emitida->numero} sobre {$bloqueado->numero}",
                'documento_facturacion',
                $emitida->id,
                nuevos: ['numero' => $emitida->numero, 'origen' => $bloqueado->numero, 'total' => $this->sumaLineas($emitida)->aDecimalString()],
                motivo: $motivo,
                autor: $usuario,
            );

            return $this->conLineas($emitida);
        });
    }

    /** @return array{documento: DocumentoFacturacion, totales: array} */
    public function detalle(DocumentoFacturacion $documento): array
    {
        return ['documento' => $this->conLineas($documento), 'totales' => $this->totales($documento)];
    }

    /** Totales del documento. Las notas se cuentan solo sobre la factura; la factura queda «anulada» cuando sus notas crédito la cubren. */
    public function totales(DocumentoFacturacion $documento): array
    {
        $total = $this->sumaLineas($documento);

        if ($documento->tipo !== TipoDocumentoFacturacion::Factura) {
            return ['total' => $total];
        }

        $totalNotaCredito = Dinero::desdeCentavos($this->documentos->sumaNotas($documento->empresa_id, $documento->id, TipoDocumentoFacturacion::NotaCredito));
        $totalNotaDebito = Dinero::desdeCentavos($this->documentos->sumaNotas($documento->empresa_id, $documento->id, TipoDocumentoFacturacion::NotaDebito));

        return [
            'total' => $total,
            'total_nota_credito' => $totalNotaCredito,
            'total_nota_debito' => $totalNotaDebito,
            'saldo' => $total->restar($totalNotaCredito)->sumar($totalNotaDebito),
            'anulada' => $total->mayorQue(Dinero::cero()) && $totalNotaCredito->mayorOIgualQue($total),
        ];
    }

    public function generarPdf(DocumentoFacturacion $documento, Usuario $usuario): PdfGenerado
    {
        $this->exigirEmitido($documento);

        $documento->load(['lineas', 'cliente.tercero', 'credito', 'origen']);

        $documentoPdf = new FacturaPdf($documento, $this->totales($documento));
        $pdf = new PdfGenerado($this->generador->generar($documentoPdf, $this->empresas->obtenerPropia()), $documentoPdf->nombreArchivo());

        $this->auditoria->registrar(
            AccionAuditoria::Exportar,
            AuditoriaConstants::MODULO_FACTURACION,
            "{$documento->tipo->etiqueta()} {$documento->numero} descargada en PDF",
            'documento_facturacion',
            $documento->id,
            autor: $usuario,
        );

        return $pdf;
    }

    private function emitir(DocumentoFacturacion $documento, Usuario $usuario): DocumentoFacturacion
    {
        $consecutivo = $this->consecutivos->siguiente($documento->tipo->consecutivo());
        $numero = trim(($consecutivo['prefijo'] ? $consecutivo['prefijo'].' ' : '').$consecutivo['numero']);

        $documento->update([
            'estado' => EstadoDocumentoFacturacion::Emitido,
            'consecutivo' => $consecutivo['numero'],
            'numero' => $numero,
            'emitido_en' => now(),
            'emitido_por' => $usuario->id,
        ]);

        return $documento;
    }

    /**
     * Líneas de una nota crédito: cada una apunta a una línea de la factura y consume de su saldo. El consumo dentro de
     * esta misma nota también cuenta, por si dos líneas apuntan a la misma.
     *
     * @return list<array{linea_origen_id: int, concepto: string, descripcion: string, valor: Dinero}>
     */
    private function lineasDeNotaCredito(DocumentoFacturacion $factura, array $lineas): array
    {
        $consumidoEnEstaNota = [];
        $resultado = [];

        foreach ($lineas as $entrada) {
            $lineaOrigen = $this->lineaDeDocumento($factura, (int) $entrada['linea_origen_id']);
            $valor = Dinero::desdePesos($entrada['valor']);

            $acreditado = Dinero::desdeCentavos($this->lineas->acreditado($factura->empresa_id, $lineaOrigen->id));
            $yaEnNota = $consumidoEnEstaNota[$lineaOrigen->id] ?? Dinero::cero();
            $restante = $lineaOrigen->valor->restar($acreditado)->restar($yaEnNota);

            if ($valor->mayorQue($restante)) {
                throw ValidationException::withMessages(['lineas' => __('validation.custom.facturacion.excede_saldo', ['concepto' => $lineaOrigen->concepto])]);
            }

            $consumidoEnEstaNota[$lineaOrigen->id] = $yaEnNota->sumar($valor);

            $resultado[] = [
                'linea_origen_id' => $lineaOrigen->id,
                'concepto' => $lineaOrigen->concepto,
                'descripcion' => $lineaOrigen->descripcion,
                'valor' => $valor,
            ];
        }

        return $resultado;
    }

    /** @return list<array{linea_origen_id: ?int, concepto: string, descripcion: string, valor: Dinero}> */
    private function lineasDeNotaDebito(DocumentoFacturacion $origen, array $lineas): array
    {
        return array_map(function (array $entrada) use ($origen) {
            $lineaOrigenId = isset($entrada['linea_origen_id']) ? $this->lineaDeDocumento($origen, (int) $entrada['linea_origen_id'])->id : null;

            return [
                'linea_origen_id' => $lineaOrigenId,
                'concepto' => $entrada['concepto'],
                'descripcion' => $entrada['descripcion'],
                'valor' => Dinero::desdePesos($entrada['valor']),
            ];
        }, $lineas);
    }

    private function lineaDeDocumento(DocumentoFacturacion $documento, int $lineaId): LineaDocumentoFacturacion
    {
        $linea = LineaDocumentoFacturacion::query()->where('documento_id', $documento->id)->whereKey($lineaId)->first();

        if ($linea === null) {
            throw ValidationException::withMessages(['lineas' => __('validation.custom.facturacion.lineas_origen_invalidas')]);
        }

        return $linea;
    }

    /** @return list<array{concepto: string, descripcion: string, valor: Dinero}> */
    private function lineasDeEntrada(array $lineas): array
    {
        return array_map(fn (array $entrada) => [
            'concepto' => $entrada['concepto'],
            'descripcion' => $entrada['descripcion'],
            'valor' => Dinero::desdePesos($entrada['valor']),
        ], $lineas);
    }

    private function sumaLineas(DocumentoFacturacion $documento): Dinero
    {
        return Dinero::desdeCentavos($this->documentos->sumaLineas($documento->id));
    }

    private function conLineas(DocumentoFacturacion $documento): DocumentoFacturacion
    {
        return $documento->load(['lineas', 'cliente.tercero', 'origen:id,numero,tipo']);
    }

    private function exigirBorrador(DocumentoFacturacion $documento): void
    {
        if ($documento->estado !== EstadoDocumentoFacturacion::Borrador) {
            throw ValidationException::withMessages(['documento' => __('validation.custom.facturacion.no_borrador')]);
        }
    }

    private function exigirEmitido(DocumentoFacturacion $documento): void
    {
        if ($documento->estado !== EstadoDocumentoFacturacion::Emitido) {
            throw ValidationException::withMessages(['documento' => __('validation.custom.facturacion.no_emitido')]);
        }
    }
}
