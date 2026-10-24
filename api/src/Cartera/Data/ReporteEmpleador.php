<?php

namespace App\Cartera\Data;

use App\Core\Dinero\Dinero;

/**
 * Relación de descuento de un empleador ya calculada, con solo datos simples: sirve igual para el PDF y para
 * la exportación a Excel, que corre en un job sin sesión y por eso no vuelve a consultar nada.
 */
final readonly class ReporteEmpleador
{
    private const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /**
     * @param  list<string>  $meses  periodos 'Y-m' en orden
     * @param  list<array{cliente_id: int, cliente_nombre: string, valor_cuota: Dinero, cuotas: array<string, array{valor: Dinero, estado: string}>}>  $filas
     * @param  array<string, Dinero>  $totalesPorMes
     */
    public function __construct(
        public int $empleadorId,
        public string $empleadorNombre,
        public ?string $empleadorNit,
        public ?string $empleadorTelefono,
        public ?string $empleadorDireccion,
        public array $meses,
        public array $filas,
        public array $totalesPorMes,
    ) {}

    /** "Sep 2026": abreviaturas fijas, sin depender del idioma del servidor. */
    public function etiquetaMes(string $periodo): string
    {
        return self::MESES[(int) substr($periodo, 5, 2) - 1].' '.substr($periodo, 0, 4);
    }
}
