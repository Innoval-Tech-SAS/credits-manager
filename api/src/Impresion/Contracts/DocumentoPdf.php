<?php

namespace App\Impresion\Contracts;

use App\Impresion\Data\ColumnaDocumento;
use App\Impresion\Data\DestinatarioDocumento;
use App\Impresion\Data\FechaDocumento;
use App\Impresion\Data\SeccionDocumento;
use App\Impresion\Data\TotalDocumento;

/**
 * Lo único que implementa un documento para imprimirse en PDF. Solo entrega datos: el marco, el encabezado
 * con la empresa, la caja de nombre y consecutivo, el destinatario y las fechas los pone la plantilla base,
 * igual para todos. Cada documento decide únicamente las columnas de la tabla y lo que hay debajo.
 */
interface DocumentoPdf
{
    /** Nombre del documento en la caja del encabezado, p. ej. "Recibo de caja". */
    public function titulo(): string;

    /** Consecutivo bajo el nombre (sin el prefijo "No."); nulo si el documento no lo tiene. */
    public function consecutivo(): ?string;

    /** Nombre del archivo descargado, sin extensión. */
    public function nombreArchivo(): string;

    public function destinatario(): DestinatarioDocumento;

    /** @return list<FechaDocumento> */
    public function fechas(): array;

    /** @return list<ColumnaDocumento> */
    public function columnas(): array;

    /**
     * Cada fila indexada por la clave de columna. Monetarios: `Dinero`; fechas: `CarbonInterface`;
     * el resto, texto o números.
     *
     * @return iterable<array<string, mixed>>
     */
    public function filas(): iterable;

    /** @return list<TotalDocumento> */
    public function totales(): array;

    /** @return list<SeccionDocumento> */
    public function secciones(): array;

    /** Texto legal al final del documento, bajo los totales. */
    public function pie(): ?string;
}
