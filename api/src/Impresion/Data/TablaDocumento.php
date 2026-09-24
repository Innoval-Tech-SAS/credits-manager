<?php

namespace App\Impresion\Data;

/** Tabla que va bajo la principal (p. ej. los pagos de un estado de cuenta), con su título y una fila final de total opcional. */
final readonly class TablaDocumento
{
    /**
     * @param  list<ColumnaDocumento>  $columnas
     * @param  list<array<string, mixed>>  $filas  cada una indexada por la clave de columna
     * @param  array<string, mixed>|null  $filaTotal  misma forma que una fila; se imprime en negrita al final
     * @param  string|null  $mensajeVacio  se imprime en lugar de las filas cuando no hay ninguna
     */
    public function __construct(
        public string $titulo,
        public array $columnas,
        public array $filas,
        public ?array $filaTotal = null,
        public ?string $mensajeVacio = null,
    ) {}
}
