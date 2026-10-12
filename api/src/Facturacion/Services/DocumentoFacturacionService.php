<?php

namespace App\Facturacion\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Services\DocumentoTerceroService;
use App\Core\Consecutivos\Services\ConsecutivoDocumentoService;
use App\Core\Dinero\Dinero;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Models\Credito;
use App\Creditos\Models\Cuota;
use App\Creditos\Repositories\CreditoRepository;
use App\Creditos\Repositories\CuotaRepository;
use App\Empresas\Models\Empresa;
use App\Empresas\Repositories\EmpresaRepository;
use App\Empresas\Services\EmpresaService;
use App\Facturacion\Documentos\FacturaPdf;
use App\Facturacion\Enums\EstadoDocumentoFacturacion;
use App\Facturacion\Enums\MotivoNotaCredito;
use App\Facturacion\Enums\MotivoNotaDebito;
use App\Facturacion\Enums\SituacionFactura;
use App\Facturacion\Enums\TipoConceptoFacturacion;
use App\Facturacion\Enums\TipoDocumentoFacturacion;
use App\Facturacion\Models\DocumentoFacturacion;
use App\Facturacion\Models\LineaDocumentoFacturacion;
use App\Facturacion\Repositories\CobroFacturacionRepository;
use App\Facturacion\Repositories\DocumentoFacturacionRepository;
use App\Facturacion\Repositories\LineaDocumentoFacturacionRepository;
use App\Facturacion\Repositories\ParametrizacionConceptoFacturacionRepository;
use App\Impresion\Data\PdfGenerado;
use App\Impresion\Services\GeneradorPdf;
use App\Terceros\Enums\TipoPersona;
use App\Terceros\Models\Tercero;
use App\Usuarios\Models\Usuario;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Facturas y notas internas. Reglas que el Service garantiza (la base no puede expresarlas):
 * - un borrador se edita y se borra; un documento emitido no cambia;
 * - el número sale de la secuencia del tipo dentro de la misma transacción de la emisión (sin huecos);
 * - una nota crédito no acredita más que lo que queda de cada línea original; si la factura ya estaba pagada, el
 *   excedente queda como saldo a favor del cliente en vez de bloquear la nota;
 * - cada emisión y nota genera su asiento en la misma transacción; sin regla contable, no se opera;
 * - la situación de la factura (emitida, parcialmente pagada, pagada, anulada) es derivada de sus notas y cobros.
 */
class DocumentoFacturacionService
{
    public function __construct(
        protected DocumentoFacturacionRepository $documentos,
        protected LineaDocumentoFacturacionRepository $lineas,
        protected CobroFacturacionRepository $cobros,
        protected CreditoRepository $creditos,
        protected CuotaRepository $cuotas,
        protected EmpresaRepository $empresaRepositorio,
        protected ParametrizacionConceptoFacturacionRepository $parametrizacionesConcepto,
        protected MotorFiscalFacturacionService $motorFiscal,
        protected ConsecutivoDocumentoService $consecutivos,
        protected AsientosFacturacionService $asientos,
        protected DocumentoTerceroService $documentosTercero,
        protected ConfiguracionFacturacionService $configuracion,
        protected EmpresaService $empresas,
        protected GeneradorPdf $generador,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** @param list<TipoDocumentoFacturacion> $tipos */
    public function listar(array $tipos, ?EstadoDocumentoFacturacion $estado, ?int $clienteId, bool $conSaldo, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $documentos = $this->documentos->paginar($tipos, $estado, $clienteId, max(1, min(100, $porPagina)), max(1, $pagina));

        if ($conSaldo) {
            $documentos->getCollection()->transform(function (DocumentoFacturacion $documento) {
                $documento->saldo = ($this->totales($documento)['saldo'] ?? Dinero::cero())->aDecimalString();

                return $documento;
            });
        }

        return $documentos;
    }

    /**
     * Conceptos sugeridos de un crédito para precargar la factura: una línea por cada componente no-cero (capital,
     * interés corriente, otros gastos) de sus cuotas pendientes o vencidas, excluyendo lo ya facturado en una
     * factura emitida. El interés de mora queda fuera a propósito: no está precalculado en la cuota y se agrega
     * a mano, como ya se hace en nota débito.
     *
     * @return list<array{cuota_id: int, tipo_concepto: string, concepto: string, descripcion: string, valor: string, fecha_vencimiento: string}>
     */
    public function conceptosSugeridos(int $creditoId): array
    {
        /** @var Credito $credito */
        $credito = $this->creditos->buscarPorIdOFallar($creditoId);
        $yaFacturados = array_flip($this->combosVigentes($credito));
        $sugerencias = [];

        foreach ($this->cuotas->pendientesOrdenadas($credito->id) as $cuota) {
            foreach ($this->componentesDeCuota($cuota) as [$tipoConcepto, $valor]) {
                if (! $valor->esPositivo() || isset($yaFacturados["{$cuota->id}|{$tipoConcepto->value}"])) {
                    continue;
                }

                $sugerencias[] = [
                    'cuota_id' => $cuota->id,
                    'tipo_concepto' => $tipoConcepto->value,
                    'concepto' => "{$tipoConcepto->etiqueta()} cuota {$cuota->numero_cuota}",
                    'descripcion' => "{$tipoConcepto->etiqueta()} de la cuota {$cuota->numero_cuota}, vence {$cuota->fecha_vencimiento->toDateString()}",
                    'valor' => $valor->aDecimalString(),
                    'fecha_vencimiento' => $cuota->fecha_vencimiento->toDateString(),
                ];
            }
        }

        return $sugerencias;
    }

    /**
     * Factura en borrador desde un crédito desembolsado (o ya pagado) de un cliente. El `Request` ya validó que
     * el crédito pertenece al cliente indicado; aquí solo se necesita el crédito, que ya trae su propio cliente.
     */
    public function crearBorrador(int $creditoId, ?string $fecha, array $lineas, ?string $fechaVencimiento, Usuario $usuario): DocumentoFacturacion
    {
        /** @var Credito $credito */
        $credito = $this->creditos->buscarPorIdOFallar($creditoId);

        if (! in_array($credito->estado, [EstadoCredito::Desembolsado, EstadoCredito::Pagado], true)) {
            throw ValidationException::withMessages(['credito_id' => __('validation.custom.facturacion.credito_no_desembolsado')]);
        }

        return DB::transaction(function () use ($credito, $fecha, $lineas, $fechaVencimiento, $usuario) {
            $documento = $this->documentos->crear([
                'tipo' => TipoDocumentoFacturacion::Factura,
                'estado' => EstadoDocumentoFacturacion::Borrador,
                'fecha' => $fecha ?? now('America/Bogota')->toDateString(),
                'fecha_vencimiento' => $fechaVencimiento,
                'cliente_id' => $credito->cliente_id,
                'credito_id' => $credito->id,
            ]);

            $this->lineas->sincronizar($documento, $this->prepararLineas($credito, $lineas, $usuario));

            $this->auditoria->registrar(
                AccionAuditoria::Crear,
                AuditoriaConstants::MODULO_FACTURACION,
                "Borrador de factura #{$documento->id} creado sobre el crédito #{$credito->id}",
                'documento_facturacion',
                $documento->id,
                nuevos: ['credito_id' => $credito->id, 'fecha' => $documento->fecha->toDateString()],
                autor: $usuario,
            );

            return $this->conLineas($documento->refresh());
        });
    }

    public function actualizarBorrador(DocumentoFacturacion $documento, ?string $fecha, array $lineas, ?string $fechaVencimiento, Usuario $usuario): DocumentoFacturacion
    {
        $this->exigirBorrador($documento);

        return DB::transaction(function () use ($documento, $fecha, $lineas, $fechaVencimiento, $usuario) {
            $bloqueado = $this->documentos->bloquear($documento->id);
            $this->exigirBorrador($bloqueado);

            if ($fecha !== null) {
                $bloqueado->update(['fecha' => $fecha]);
            }

            if ($fechaVencimiento !== null) {
                $bloqueado->update(['fecha_vencimiento' => $fechaVencimiento]);
            }

            /** @var Credito $credito */
            $credito = $this->creditos->buscarPorIdOFallar($bloqueado->credito_id);

            $this->lineas->sincronizar($bloqueado, $this->prepararLineas($credito, $lineas, $usuario));

            $this->auditoria->registrar(
                AccionAuditoria::Modificar,
                AuditoriaConstants::MODULO_FACTURACION,
                "Borrador de factura #{$bloqueado->id} modificado",
                'documento_facturacion',
                $bloqueado->id,
                nuevos: ['fecha' => $bloqueado->fecha->toDateString(), 'lineas' => count($lineas)],
                autor: $usuario,
            );

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

            $this->auditoria->registrar(
                AccionAuditoria::Eliminar,
                AuditoriaConstants::MODULO_FACTURACION,
                "Borrador de factura #{$bloqueado->id} eliminado",
                'documento_facturacion',
                $bloqueado->id,
            );
        });
    }

    /** Emite la factura: asigna el número de la secuencia de facturas, la deja inmutable y la asienta. */
    public function emitirFactura(DocumentoFacturacion $documento, Usuario $usuario): DocumentoFacturacion
    {
        $this->exigirBorrador($documento);

        return DB::transaction(function () use ($documento, $usuario) {
            $bloqueado = $this->documentos->bloquear($documento->id);
            $this->exigirBorrador($bloqueado);

            if ($this->documentos->sumaLineas($bloqueado->id) <= 0) {
                throw ValidationException::withMessages(['lineas' => __('validation.custom.facturacion.sin_lineas')]);
            }

            $this->exigirTerceroFiscalCompleto($bloqueado->cliente->tercero);

            $emitido = $this->emitir($bloqueado, $usuario);
            $totales = $this->totales($this->conLineas($emitido));
            $asiento = $this->asientos->contabilizarEmision($emitido, $totales);

            $documentoTercero = $this->documentosTercero->crearDocumento(
                $emitido->cliente->tercero,
                NaturalezaDocumento::PorCobrar,
                'factura',
                $emitido->numero,
                $emitido->fecha->toDateString(),
                "Factura {$emitido->numero}",
                $totales['total'],
                'facturacion',
                $emitido->id,
                planCuentaId: $this->asientos->cuentaCxcFactura(),
                fechaVencimiento: $emitido->fecha_vencimiento?->toDateString(),
            );

            $emitido->update(['comprobante_id' => $asiento->id, 'documento_tercero_id' => $documentoTercero->id]);

            $this->auditoria->registrar(
                AccionAuditoria::Crear,
                AuditoriaConstants::MODULO_FACTURACION,
                "Factura {$emitido->numero} emitida",
                'documento_facturacion',
                $emitido->id,
                nuevos: ['numero' => $emitido->numero, 'credito_id' => $emitido->credito_id, 'total' => $totales['total']->aDecimalString()],
                autor: $usuario,
            );

            return $this->conLineas($emitido);
        });
    }

    /**
     * Datos obligatorios para la factura electrónica (aunque el MVP no transmita a la DIAN, se exigen desde ya):
     * identificación, nombre, dirección, correo y responsabilidad fiscal; el dígito de verificación solo si es
     * persona jurídica (la persona natural no lo tiene).
     */
    private function exigirTerceroFiscalCompleto(Tercero $tercero): void
    {
        $faltanDatosBase = blank($tercero->nombre) || blank($tercero->numero_documento) || blank($tercero->tipo_persona)
            || blank($tercero->direccion) || blank($tercero->correo);

        $faltaDigitoVerificacion = $tercero->tipo_persona === TipoPersona::Juridica && blank($tercero->digito_verificacion);

        if ($faltanDatosBase || $faltaDigitoVerificacion) {
            throw ValidationException::withMessages(['documento' => __('validation.custom.facturacion.tercero_fiscal_incompleto')]);
        }
    }

    /**
     * Nota crédito sobre una factura, o nota débito sobre una factura o una nota crédito.
     * Nota crédito: cada línea corrige una línea de la factura, con el concepto de esa línea y sin pasarse de lo que queda.
     * Nota débito: líneas libres (concepto, descripción y valor), opcionalmente vinculadas a una línea de origen.
     * El motivo sale de la lista cerrada del tipo (`MotivoNotaCredito`/`MotivoNotaDebito`); con «otros» se guarda el
     * detalle en texto libre, si no, la etiqueta del motivo elegido.
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    public function emitirNota(
        DocumentoFacturacion $origen,
        TipoDocumentoFacturacion $tipo,
        string $motivoCodigo,
        ?string $motivoDetalle,
        ?string $fechaVencimiento,
        array $lineas,
        Usuario $usuario,
    ): DocumentoFacturacion {
        $this->exigirEmitido($origen);

        $tipoOrigenValido = $tipo === TipoDocumentoFacturacion::NotaCredito
            ? $origen->tipo === TipoDocumentoFacturacion::Factura
            : in_array($origen->tipo, [TipoDocumentoFacturacion::Factura, TipoDocumentoFacturacion::NotaCredito], true);

        if (! $tipoOrigenValido) {
            throw ValidationException::withMessages(['documento' => __('validation.custom.facturacion.origen_tipo_invalido')]);
        }

        $motivo = $motivoDetalle ?? $this->etiquetaMotivo($tipo, $motivoCodigo);

        return DB::transaction(function () use ($origen, $tipo, $motivo, $fechaVencimiento, $lineas, $usuario) {
            // Bloquear el origen: dos notas o cobros simultáneos sobre la misma factura se serializan y no acreditan dos veces el mismo saldo.
            $bloqueado = $this->documentos->bloquear($origen->id);
            $this->exigirEmitido($bloqueado);

            $lineasNota = $tipo === TipoDocumentoFacturacion::NotaCredito
                ? $this->lineasDeNotaCredito($bloqueado, $lineas)
                : $this->lineasDeNotaDebito($bloqueado, $lineas);

            if ($tipo === TipoDocumentoFacturacion::NotaDebito) {
                $this->exigirDentroDelTope($lineasNota, (int) $usuario->empresa_id);
            }

            return $this->crearNota($bloqueado, $tipo, $motivo, $lineasNota, $usuario, fechaVencimiento: $fechaVencimiento);
        });
    }

    /**
     * Anula una factura con una nota crédito por el saldo de cada una de sus líneas. Exige que no tenga cobros
     * vigentes ni notas débito: esos movimientos se reversan primero, para que el asiento de la anulación sea el
     * reverso exacto del saldo. La factura conserva su número.
     */
    public function anular(DocumentoFacturacion $factura, string $motivo, Usuario $usuario): DocumentoFacturacion
    {
        return DB::transaction(function () use ($factura, $motivo, $usuario) {
            $bloqueado = $this->documentos->bloquear($factura->id);
            $this->exigirEmitido($bloqueado);

            if ($bloqueado->tipo !== TipoDocumentoFacturacion::Factura) {
                throw ValidationException::withMessages(['documento' => __('validation.custom.facturacion.solo_factura_anulable')]);
            }

            $totales = $this->totales($bloqueado);

            if ($totales['anulada']) {
                throw ValidationException::withMessages(['documento' => __('validation.custom.facturacion.ya_anulada')]);
            }

            if ($totales['total_cobrado']->esPositivo()) {
                throw ValidationException::withMessages(['documento' => __('validation.custom.facturacion.con_cobros')]);
            }

            if ($totales['total_nota_debito']->esPositivo()) {
                throw ValidationException::withMessages(['documento' => __('validation.custom.facturacion.con_notas_debito')]);
            }

            $porLinea = [];

            foreach ($bloqueado->lineas as $linea) {
                $restante = $linea->valor->restar(Dinero::desdeCentavos($this->lineas->acreditado($bloqueado->empresa_id, $linea->id)));

                if ($restante->esPositivo()) {
                    $porLinea[] = ['linea_origen_id' => $linea->id, 'valor' => $restante->aDecimalString()];
                }
            }

            $this->crearNota($bloqueado, TipoDocumentoFacturacion::NotaCredito, $motivo, $this->lineasDeNotaCredito($bloqueado, $porLinea), $usuario, anulacion: true);

            return $this->conLineas($bloqueado->refresh());
        });
    }

    /** @return array{documento: DocumentoFacturacion, totales: array, cobros: Collection} */
    public function detalle(DocumentoFacturacion $documento): array
    {
        return [
            'documento' => $this->conLineas($documento),
            'totales' => $this->totales($documento),
            'cobros' => $this->cobros->listarDelDocumento($documento->id),
        ];
    }

    /**
     * Totales del documento. Las notas y los cobros se cuentan solo sobre la factura.
     * La situación se deriva: anulada (las notas crédito cubren el total), pagada (saldo cero o menos),
     * parcialmente pagada (hay notas crédito o cobros y queda saldo) o emitida.
     */
    public function totales(DocumentoFacturacion $documento): array
    {
        $subtotal = $this->sumaLineas($documento);

        if ($documento->tipo !== TipoDocumentoFacturacion::Factura) {
            return ['total' => $subtotal];
        }

        $impuestos = $this->documentos->sumaImpuestos($documento->id);
        $totalIva = Dinero::desdeCentavos($impuestos['iva']);
        $totalRetencion = Dinero::desdeCentavos($impuestos['retefuente'] + $impuestos['reteica'] + $impuestos['reteiva']);
        // Neto: lo que realmente debe el cliente (lo que entra a CxC y a los cobros).
        $total = $subtotal->sumar($totalIva)->restar($totalRetencion);

        $totalNotaCredito = Dinero::desdeCentavos($this->documentos->sumaNotas($documento->empresa_id, $documento->id, TipoDocumentoFacturacion::NotaCredito));
        $totalNotaDebito = Dinero::desdeCentavos($this->documentos->sumaNotas($documento->empresa_id, $documento->id, TipoDocumentoFacturacion::NotaDebito));
        $totalCobrado = Dinero::desdeCentavos($this->cobros->cobradoNeto($documento->id));

        $saldo = $total->restar($totalNotaCredito)->sumar($totalNotaDebito)->restar($totalCobrado);
        // La nota crédito acredita contra el bruto de cada línea (ver lineasDeNotaCredito), no contra el neto con impuestos.
        $anulada = $subtotal->mayorQue(Dinero::cero()) && $totalNotaCredito->mayorOIgualQue($subtotal);

        return [
            'subtotal' => $subtotal,
            'total_iva' => $totalIva,
            'total_retencion' => $totalRetencion,
            'total' => $total,
            'total_nota_credito' => $totalNotaCredito,
            'total_nota_debito' => $totalNotaDebito,
            'total_cobrado' => $totalCobrado,
            'saldo' => $saldo,
            'saldo_a_favor' => $saldo->esNegativo() ? $saldo->absoluto() : Dinero::cero(),
            'anulada' => $anulada,
            'situacion' => $this->situacion($anulada, $saldo, $totalNotaCredito->sumar($totalCobrado)),
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

    /** Para el job de envío por correo: sin sesión (la empresa llega explícita) y sin auditoría de descarga, porque nadie la pidió. */
    public function generarPdfInterno(DocumentoFacturacion $documento, Empresa $empresa): PdfGenerado
    {
        $this->exigirEmitido($documento);

        $documento->load(['lineas', 'cliente.tercero', 'credito', 'origen']);

        $documentoPdf = new FacturaPdf($documento, $this->totales($documento));

        return new PdfGenerado($this->generador->generar($documentoPdf, $empresa), $documentoPdf->nombreArchivo());
    }

    /**
     * Crea la nota emitida y su asiento. Una nota débito no se emite sobre una factura anulada. Una nota crédito no
     * supera el valor de la factura (el tope real ya lo exige `lineasDeNotaCredito()` por línea, contra la línea de
     * la factura, no contra lo pendiente de cobro): si la factura ya estaba pagada, el excedente no se aplica al
     * documento por cobrar (no puede quedar en saldo negativo) y queda como saldo a favor del cliente, visible en
     * `totales()`, sin cruzarlo automáticamente — eso lo hace el usuario desde Anticipos si decide devolverlo.
     */
    private function crearNota(DocumentoFacturacion $origen, TipoDocumentoFacturacion $tipo, string $motivo, array $lineasNota, Usuario $usuario, bool $anulacion = false, ?string $fechaVencimiento = null): DocumentoFacturacion
    {
        if ($origen->tipo === TipoDocumentoFacturacion::Factura) {
            $totales = $this->totales($origen);

            if ($tipo === TipoDocumentoFacturacion::NotaDebito && $totales['anulada']) {
                throw ValidationException::withMessages(['documento' => __('validation.custom.facturacion.ya_anulada')]);
            }
        }

        $nota = $this->documentos->crear([
            'tipo' => $tipo,
            'estado' => EstadoDocumentoFacturacion::Borrador,
            'fecha' => now('America/Bogota')->toDateString(),
            'fecha_vencimiento' => $tipo === TipoDocumentoFacturacion::NotaDebito ? $fechaVencimiento : null,
            'cliente_id' => $origen->cliente_id,
            'credito_id' => $origen->credito_id,
            'documento_origen_id' => $origen->id,
            'motivo' => $motivo,
        ]);

        $this->lineas->crearParaDocumento($nota, $lineasNota);

        $emitida = $this->emitir($nota, $usuario);
        $valorNota = $this->sumaLineas($emitida);
        $asiento = $this->asientos->contabilizarNota($emitida, $valorNota);
        $emitida->update(['comprobante_id' => $asiento->id]);

        $factura = $origen->tipo === TipoDocumentoFacturacion::Factura ? $origen : $origen->origen;

        if ($factura?->documento_tercero_id && $valorNota->esPositivo()) {
            if ($tipo === TipoDocumentoFacturacion::NotaCredito) {
                // No puede exceder el saldo del documento por cobrar: lo que sobre queda como saldo a favor, sin aplicar.
                $valorAAplicar = $valorNota->menorEntre($factura->documentoTercero->saldo);

                if ($valorAAplicar->esPositivo()) {
                    $this->documentosTercero->aplicarValor(
                        $factura->cliente->tercero,
                        NaturalezaDocumento::PorCobrar,
                        [['documento_tercero_id' => $factura->documento_tercero_id, 'valor' => $valorAAplicar]],
                        'facturacion_nota',
                        $emitida->id,
                        $emitida->fecha->toDateString(),
                    );
                }
            } else {
                $this->documentosTercero->incrementarValor(
                    $factura->documentoTercero,
                    $valorNota,
                    'facturacion_nota',
                    $emitida->id,
                    $emitida->fecha->toDateString(),
                );
            }
        }

        $descripcion = $anulacion
            ? "Factura {$origen->numero} anulada con {$emitida->numero}"
            : "{$tipo->etiqueta()} {$emitida->numero} sobre {$origen->numero}";

        $this->auditoria->registrar(
            $tipo === TipoDocumentoFacturacion::NotaCredito ? AccionAuditoria::Anular : AccionAuditoria::Crear,
            AuditoriaConstants::MODULO_FACTURACION,
            $descripcion,
            'documento_facturacion',
            $emitida->id,
            nuevos: ['numero' => $emitida->numero, 'origen' => $origen->numero, 'total' => $this->sumaLineas($emitida)->aDecimalString()],
            motivo: $motivo,
            autor: $usuario,
        );

        return $this->conLineas($emitida);
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
    /** @param  list<array{valor: Dinero, ...}>  $lineasNota */
    private function exigirDentroDelTope(array $lineasNota, int $empresaId): void
    {
        $tope = $this->configuracion->parametros($empresaId)->notaDebitoTope;
        $total = Dinero::sumarVarios(...array_column($lineasNota, 'valor'));

        if ($total->mayorQue($tope)) {
            throw ValidationException::withMessages([
                'lineas' => __('validation.custom.facturacion.nota_debito_excede_tope', ['tope' => $tope->aDecimalString()]),
            ]);
        }
    }

    private function lineasDeNotaDebito(DocumentoFacturacion $origen, array $lineas): array
    {
        return array_map(function (array $entrada) use ($origen) {
            $lineaOrigenId = isset($entrada['linea_origen_id']) ? $this->lineaDeDocumento($origen, (int) $entrada['linea_origen_id'])->id : null;

            return [
                'linea_origen_id' => $lineaOrigenId,
                'concepto' => $entrada['concepto'],
                'descripcion' => $entrada['descripcion'],
                'valor' => Dinero::desdePesos($entrada['valor']),
                'tasa_mora' => $entrada['tasa_mora'] ?? null,
                'dias_mora' => $entrada['dias_mora'] ?? null,
                'base_mora' => isset($entrada['base_mora']) ? Dinero::desdePesos($entrada['base_mora']) : null,
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

    /**
     * Valida duplicados y el permiso de modificar un valor sugerido, calcula IVA/retenciones por línea (motor
     * fiscal) y deja las líneas listas para `LineaDocumentoFacturacionRepository`. Se recalcula siempre con la
     * parametrización vigente: solo lo que ya quedó emitido es inmutable.
     *
     * @return list<array<string, mixed>>
     */
    private function prepararLineas(Credito $credito, array $lineas, Usuario $usuario): array
    {
        $empresa = $this->empresaRepositorio->buscarPorIdOFallar($credito->empresa_id);
        $tercero = $credito->cliente->tercero;
        $parametrizaciones = $this->parametrizacionesConcepto->paraEmpresa($credito->empresa_id);
        $sugeridos = collect($this->conceptosSugeridos($credito->id))->keyBy(fn (array $s) => "{$s['cuota_id']}|{$s['tipo_concepto']}");
        $yaFacturados = array_flip($this->combosVigentes($credito));
        $fecha = now('America/Bogota')->toDateString();
        $overrides = [];

        $resultado = array_map(function (array $entrada) use ($empresa, $tercero, $parametrizaciones, $sugeridos, $yaFacturados, $fecha, $usuario, &$overrides) {
            $tipoConcepto = TipoConceptoFacturacion::from($entrada['tipo_concepto']);
            $cuotaId = isset($entrada['cuota_id']) ? (int) $entrada['cuota_id'] : null;
            $valor = Dinero::desdePesos($entrada['valor']);

            if ($cuotaId !== null) {
                $clave = "{$cuotaId}|{$tipoConcepto->value}";

                if (isset($yaFacturados[$clave])) {
                    throw ValidationException::withMessages([
                        'lineas' => __('validation.custom.facturacion.concepto_ya_facturado', ['concepto' => $entrada['concepto']]),
                    ]);
                }

                $sugerido = $sugeridos->get($clave);

                if ($sugerido !== null && ! $valor->igualA(Dinero::desdePesos($sugerido['valor']))) {
                    // Permiso, no regla de negocio del contenido: igual que ImportacionService::generarAsiento, se corta con 403.
                    abort_unless($usuario->tienePermiso('facturacion', 'modificar_valores_sugeridos'), 403, 'No tienes permiso para realizar esta acción.');

                    $overrides[] = ['concepto' => $entrada['concepto'], 'sugerido' => $sugerido['valor'], 'nuevo' => $valor->aDecimalString()];
                }
            }

            $impuestos = $this->motorFiscal->evaluar($valor, $empresa, $tercero, $parametrizaciones->get($tipoConcepto->value), $fecha);

            return [
                'cuota_id' => $cuotaId,
                'tipo_concepto' => $tipoConcepto,
                'concepto' => $entrada['concepto'],
                'descripcion' => $entrada['descripcion'],
                'valor' => $valor,
                'valor_iva' => $impuestos['valor_iva'],
                'valor_retefuente' => $impuestos['valor_retefuente'],
                'valor_reteica' => $impuestos['valor_reteica'],
                'valor_reteiva' => $impuestos['valor_reteiva'],
            ];
        }, $lineas);

        if ($overrides !== []) {
            $this->auditoria->registrar(
                AccionAuditoria::Modificar,
                AuditoriaConstants::MODULO_FACTURACION,
                "Valores sugeridos modificados al facturar el crédito #{$credito->id}",
                'documento_facturacion',
                null,
                nuevos: ['credito_id' => $credito->id, 'overrides' => $overrides],
                autor: $usuario,
            );
        }

        return $resultado;
    }

    /**
     * Combos cuota/concepto ya facturados que siguen vigentes: si la factura que los contiene quedó anulada
     * (nota crédito que cubre todo su subtotal), el combo vuelve a estar disponible para una factura nueva.
     *
     * @return list<string> claves "cuota_id|tipo_concepto"
     */
    private function combosVigentes(Credito $credito): array
    {
        $filas = $this->lineas->combosFacturados($credito->empresa_id, $credito->id);

        if ($filas->isEmpty()) {
            return [];
        }

        $anuladas = $this->documentos->facturasEmitidasDelCredito($credito->id)
            ->filter(fn (DocumentoFacturacion $factura) => $this->totales($factura)['anulada'])
            ->pluck('id');

        return $filas
            ->reject(fn ($fila) => $anuladas->contains($fila->documento_id))
            ->map(fn ($fila) => "{$fila->cuota_id}|{$fila->tipo_concepto}")
            ->all();
    }

    /** @return list<array{0: TipoConceptoFacturacion, 1: Dinero}> */
    private function componentesDeCuota(Cuota $cuota): array
    {
        return [
            [TipoConceptoFacturacion::Capital, $cuota->valor_capital],
            [TipoConceptoFacturacion::InteresCorriente, $cuota->valor_interes],
            [TipoConceptoFacturacion::OtrosGastos, $cuota->valor_otros_gastos],
        ];
    }

    private function etiquetaMotivo(TipoDocumentoFacturacion $tipo, string $codigo): string
    {
        return $tipo === TipoDocumentoFacturacion::NotaCredito
            ? MotivoNotaCredito::from($codigo)->etiqueta()
            : MotivoNotaDebito::from($codigo)->etiqueta();
    }

    private function situacion(bool $anulada, Dinero $saldo, Dinero $liquidado): SituacionFactura
    {
        if ($anulada) {
            return SituacionFactura::Anulada;
        }

        if ($saldo->esNegativo()) {
            return SituacionFactura::ConSaldoAFavor;
        }

        if (! $saldo->esPositivo()) {
            return SituacionFactura::Pagada;
        }

        return $liquidado->esPositivo() ? SituacionFactura::ParcialmentePagada : SituacionFactura::Emitida;
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
