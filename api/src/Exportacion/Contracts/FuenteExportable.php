<?php

namespace App\Exportacion\Contracts;

use App\Exportacion\Data\ColumnaExportable;
use App\Usuarios\Models\Usuario;

/**
 * Lo único que implementa un reporte para poder exportarse. Debe ser serializable (viaja dentro
 * del job de la cola) y llevar de forma explícita su visibilidad y filtros: el worker no tiene sesión.
 * Debe reutilizar la misma consulta y reglas de visibilidad que el listado en pantalla.
 */
interface FuenteExportable
{
    /** Clave estable del reporte, p. ej. 'auditoria.bitacora'. */
    public function reporte(): string;

    /** Módulo al que se atribuye el evento de auditoría de la exportación. */
    public function modulo(): string;

    public function titulo(): string;

    /** Nombre base del archivo, sin extensión ni fecha. */
    public function nombreArchivo(): string;

    /** @return list<ColumnaExportable> */
    public function columnas(): array;

    /**
     * Filas de forma perezosa (cursor/lazy, nunca cargar todo en memoria), cada una indexada por
     * la clave de columna. Monetarios: `Dinero` o cadena decimal; fechas: DateTimeInterface o string.
     *
     * @return iterable<array<string, mixed>>
     */
    public function filas(): iterable;

    public function totalFilas(): int;

    /** @return array<string, scalar|null> filtros aplicados, en formato legible */
    public function filtrosAplicados(): array;

    public function autorizadoPara(Usuario $usuario): bool;
}
