<?php

namespace App\Auditoria\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Data\EventoAuditoria;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Repositories\BitacoraAuditoriaRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

/**
 * Panel de recuperación (solo superadmin: el log mezcla eventos de todas las empresas). Un evento del
 * log está "recuperado" cuando su evento_id ya existe en la bitácora; no hay estado propio que sincronizar.
 * Recuperar re-emite el evento original (mismo evento_id, fecha y autor) al stream: es idempotente.
 */
class RecuperacionAuditoriaService
{
    public const PENDIENTE = 'pendiente';

    public const RECUPERADO = 'recuperado';

    public const NO_RECUPERABLE = 'no_recuperable';

    public function __construct(
        protected RespaldoAuditoriaService $respaldo,
        protected EmisorAuditoria $emisor,
        protected BitacoraAuditoriaRepository $bitacora,
    ) {}

    public function listar(?string $estado, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $entradas = collect($this->entradasConEstado())
            ->when($estado, fn ($coleccion, string $estado) => $coleccion->where('estado', $estado))
            ->values();

        return new LengthAwarePaginator(
            $entradas->forPage($pagina, $porPagina)->values(),
            $entradas->count(),
            $porPagina,
            $pagina,
        );
    }

    /**
     * @param  list<string>|null  $eventoIds  null = todos los pendientes
     * @return array{recuperados: int, fallidos: int}
     */
    public function recuperar(Usuario $superadmin, ?array $eventoIds): array
    {
        $pendientes = collect($this->entradasConEstado())
            ->where('estado', self::PENDIENTE)
            ->when($eventoIds !== null, fn ($coleccion) => $coleccion->whereIn('evento_id', $eventoIds));

        $recuperados = [];
        $fallidos = 0;

        foreach ($pendientes as $entrada) {
            try {
                $this->emisor->publicar(EventoAuditoria::desdeArray($entrada['evento']));
                $recuperados[] = $entrada['evento_id'];
            } catch (Throwable) {
                $fallidos++;
            }
        }

        if ($recuperados !== []) {
            $this->emisor->registrar(
                AccionAuditoria::Recuperar,
                AuditoriaConstants::MODULO,
                'Recuperación de '.count($recuperados).' evento(s) desde el log de respaldo de auditoría',
                nuevos: ['evento_ids' => $recuperados],
                autor: $superadmin,
            );
        }

        return ['recuperados' => count($recuperados), 'fallidos' => $fallidos];
    }

    /** @return list<array<string, mixed>> */
    private function entradasConEstado(): array
    {
        $vistas = [];

        foreach ($this->respaldo->entradas() as $entrada) {
            $clave = $entrada['evento_id'] ?? 'sin-id-'.count($vistas);

            // El log viene de la más reciente a la más antigua: si un evento aparece dos veces, vale la reciente.
            $vistas[$clave] ??= $entrada;
        }

        $ids = array_keys(array_filter($vistas, fn (array $entrada) => $entrada['evento'] !== null));
        $existentes = [];

        foreach (array_chunk($ids, 1000) as $lote) {
            $existentes = [...$existentes, ...$this->bitacora->eventoIdsExistentes($lote)];
        }

        $existentes = array_flip($existentes);

        return array_values(array_map(function (array $entrada) use ($existentes) {
            $evento = $entrada['evento'];

            return [
                'evento_id' => $entrada['evento_id'],
                'registrado_en' => $entrada['registrado_en'],
                'motivo_fallo' => $entrada['motivo_fallo'],
                'error' => $entrada['error'],
                'estado' => match (true) {
                    $evento === null => self::NO_RECUPERABLE,
                    isset($existentes[$entrada['evento_id']]) => self::RECUPERADO,
                    default => self::PENDIENTE,
                },
                'evento' => $evento,
                'payload_crudo' => $entrada['payload_crudo'],
                'descripcion' => $evento['descripcion'] ?? ($entrada['payload_crudo']['descripcion'] ?? null),
                'modulo' => $evento['modulo'] ?? ($entrada['payload_crudo']['modulo'] ?? null),
                'accion' => $evento['accion'] ?? ($entrada['payload_crudo']['accion'] ?? null),
            ];
        }, $vistas));
    }
}
