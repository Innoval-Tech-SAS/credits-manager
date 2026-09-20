<?php

namespace App\Auditoria\Data;

use App\Auditoria\Enums\AccionAuditoria;
use Carbon\CarbonImmutable;

final readonly class FiltrosBitacora
{
    public function __construct(
        public ?CarbonImmutable $desde = null,
        public ?CarbonImmutable $hasta = null,
        public ?string $modulo = null,
        public ?AccionAuditoria $accion = null,
        public ?string $autor = null,
        public ?string $entidad = null,
        public ?int $empresaId = null,
    ) {}

    /** @param array<string, mixed> $validados salida de ListarBitacoraRequest / ExportarBitacoraRequest */
    public static function desdeValidados(array $validados): self
    {
        $zona = config('app.timezone');

        return new self(
            desde: isset($validados['desde']) ? CarbonImmutable::parse($validados['desde'], $zona)->startOfDay() : null,
            hasta: isset($validados['hasta']) ? CarbonImmutable::parse($validados['hasta'], $zona)->endOfDay() : null,
            modulo: $validados['modulo'] ?? null,
            accion: isset($validados['accion']) ? AccionAuditoria::from($validados['accion']) : null,
            autor: $validados['autor'] ?? null,
            entidad: $validados['entidad'] ?? null,
            empresaId: isset($validados['empresa_id']) ? (int) $validados['empresa_id'] : null,
        );
    }

    /** Solo los filtros presentes, en formato legible (hoja "Parámetros", tabla `exportaciones`, evento de auditoría). */
    public function aArray(): array
    {
        return array_filter([
            'desde' => $this->desde?->format('Y-m-d'),
            'hasta' => $this->hasta?->format('Y-m-d'),
            'modulo' => $this->modulo,
            'accion' => $this->accion?->value,
            'autor' => $this->autor,
            'entidad' => $this->entidad,
            'empresa_id' => $this->empresaId,
        ], fn ($valor) => $valor !== null);
    }
}
