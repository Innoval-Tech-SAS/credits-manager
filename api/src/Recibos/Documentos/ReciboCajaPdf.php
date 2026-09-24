<?php

namespace App\Recibos\Documentos;

use App\Impresion\Contracts\DocumentoPdf;
use App\Impresion\Data\ColumnaDocumento;
use App\Impresion\Data\DestinatarioDocumento;
use App\Impresion\Data\FechaDocumento;
use App\Impresion\Data\SeccionDocumento;
use App\Impresion\Data\TotalDocumento;
use App\Impresion\Enums\OrientacionDocumento;
use App\Impresion\Enums\TipoColumnaDocumento;
use App\Impresion\Services\NumeroALetras;
use App\Pagos\Models\Pago;
use Illuminate\Support\Str;

/** Recibo de caja de un pago. Espera el pago con `recibo.comprobante`, `cliente.tercero`, `detalles.cuota` y `registrador` ya cargados. */
final class ReciboCajaPdf implements DocumentoPdf
{
    public function __construct(private readonly Pago $pago) {}

    public function titulo(): string
    {
        return 'Recibo de caja';
    }

    public function consecutivo(): ?string
    {
        return $this->pago->recibo->numero;
    }

    public function nombreArchivo(): string
    {
        return 'recibo-de-caja-'.Str::slug($this->pago->recibo->numero);
    }

    public function orientacion(): OrientacionDocumento
    {
        return OrientacionDocumento::Vertical;
    }

    public function destinatario(): DestinatarioDocumento
    {
        $tercero = $this->pago->cliente->tercero;

        return new DestinatarioDocumento(
            nombre: $tercero->nombre,
            documento: $tercero->numero_documento,
            telefono: $tercero->celular ?: $tercero->telefono_fijo,
            direccion: $tercero->direccion,
            ciudad: $tercero->ciudad,
            etiquetaDocumento: strtoupper($tercero->tipo_documento->value),
        );
    }

    public function fechas(): array
    {
        return [
            new FechaDocumento('Fecha de pago', $this->pago->fecha_pago, true),
            new FechaDocumento('Expedición', $this->pago->recibo->creado_en, true),
        ];
    }

    public function tituloTabla(): ?string
    {
        return null;
    }

    public function columnas(): array
    {
        return [
            new ColumnaDocumento('item', 'Ítem', TipoColumnaDocumento::Entero, 8),
            new ColumnaDocumento('descripcion', 'Descripción', TipoColumnaDocumento::Texto, 62),
            new ColumnaDocumento('valor', 'Vr. Aplicado', TipoColumnaDocumento::Monetario, 30),
        ];
    }

    public function filas(): iterable
    {
        // La liquidación anticipada cierra las cuotas sin detalle por cuota: se imprime el pago completo en una línea.
        if ($this->pago->detalles->isEmpty()) {
            yield ['item' => 1, 'descripcion' => "Pago del crédito #{$this->pago->credito_id}", 'valor' => $this->pago->valor_pago];

            return;
        }

        foreach ($this->pago->detalles->values() as $indice => $detalle) {
            $cuota = $detalle->cuota;

            yield [
                'item' => $indice + 1,
                'descripcion' => "Cuota {$cuota->numero_cuota} del crédito #{$this->pago->credito_id}",
                'valor' => $detalle->valor_aplicado,
            ];
        }
    }

    public function tablasAdicionales(): array
    {
        return [];
    }

    public function totales(): array
    {
        return [new TotalDocumento('Total recibido', $this->pago->valor_pago)];
    }

    public function secciones(): array
    {
        $secciones = [
            new SeccionDocumento('Valor en Letras', NumeroALetras::pesos($this->pago->valor_pago)),
            new SeccionDocumento('Forma de pago', ucfirst($this->pago->forma_pago->value)),
        ];

        $cuentaCajaBanco = $this->cuentaDeCajaOBanco();

        if ($cuentaCajaBanco) {
            $secciones[] = new SeccionDocumento('Cuenta de caja o banco', $cuentaCajaBanco);
        }

        $comprobante = $this->pago->recibo->comprobante;

        if ($comprobante) {
            $secciones[] = new SeccionDocumento('Comprobante contable', "{$comprobante->tipoComprobante->codigo} {$comprobante->consecutivo}");
        }

        if ($this->pago->registrador) {
            $secciones[] = new SeccionDocumento('Recibido por', $this->pago->registrador->nombre);
        }

        if ($this->pago->observacion) {
            $secciones[] = new SeccionDocumento('Observaciones', $this->pago->observacion);
        }

        return $secciones;
    }

    public function pie(): ?string
    {
        return 'Conserve este recibo como soporte de su pago. No corresponde a una factura electrónica de venta.';
    }

    /** La cuenta que se debitó en el asiento del recaudo: donde entró el dinero. */
    private function cuentaDeCajaOBanco(): ?string
    {
        $linea = $this->pago->recibo->comprobante?->lineas->first(fn ($asiento) => $asiento->debito->esPositivo());

        return $linea?->cuenta ? "{$linea->cuenta->codigo} {$linea->cuenta->nombre}" : null;
    }
}
