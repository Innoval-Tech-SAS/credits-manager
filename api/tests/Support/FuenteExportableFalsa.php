<?php

namespace Tests\Support;

use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Usuarios\Models\Usuario;
use RuntimeException;

/** Fuente arbitraria para probar el módulo Exportacion sin depender de ningún reporte real. */
final class FuenteExportableFalsa implements FuenteExportable
{
    /** @param list<ColumnaExportable> $columnas @param list<array<string, mixed>> $filas */
    public function __construct(
        private readonly array $columnas,
        private readonly array $filas,
        private readonly bool $fallaAlLeer = false,
        private readonly bool $autorizada = true,
    ) {}

    public function reporte(): string
    {
        return 'pruebas.reporte';
    }

    public function modulo(): string
    {
        return 'pruebas';
    }

    public function titulo(): string
    {
        return 'Reporte de pruebas';
    }

    public function nombreArchivo(): string
    {
        return 'reporte-pruebas';
    }

    public function columnas(): array
    {
        return $this->columnas;
    }

    public function filas(): iterable
    {
        foreach ($this->filas as $indice => $fila) {
            if ($this->fallaAlLeer && $indice === 1) {
                throw new RuntimeException('Falla a mitad de la lectura');
            }

            yield $fila;
        }
    }

    public function totalFilas(): int
    {
        return count($this->filas);
    }

    public function filtrosAplicados(): array
    {
        return ['periodo' => '2026-03', 'estado' => 'activo'];
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return $this->autorizada;
    }
}
