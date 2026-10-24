<?php

namespace App\Auditoria\Repositories;

use App\Auditoria\Data\EventoAuditoria;
use App\Auditoria\Data\FiltrosBitacora;
use App\Auditoria\Data\Visibilidad;
use App\Auditoria\Models\BitacoraAuditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

/**
 * No extiende BaseRepository: la bitácora es de solo inserción y no debe exponer
 * actualizar/eliminar. `BitacoraAuditoria` no lleva EmpresaScope; toda lectura pasa por
 * `consultaVisible`, la única excepción declarada al aislamiento por empresa.
 */
class BitacoraAuditoriaRepository
{
    /** @return bool false si el evento ya estaba insertado (idempotencia por evento_id). */
    public function insertarSiNoExiste(EventoAuditoria $evento): bool
    {
        try {
            BitacoraAuditoria::query()->create([
                'evento_id' => $evento->eventoId,
                'empresa_id' => $evento->empresaId,
                'usuario_id' => $evento->autor->usuarioId,
                'autor_nombre' => $evento->autor->nombre,
                'autor_usuario' => $evento->autor->usuario,
                'autor_rol' => $evento->autor->rol,
                'es_superadmin' => $evento->autor->esSuperadmin,
                'modulo' => $evento->modulo,
                'accion' => $evento->accion,
                'descripcion' => $evento->descripcion,
                'motivo' => $evento->motivo,
                'entidad' => $evento->entidad,
                'entidad_id' => $evento->entidadId,
                'valores_anteriores' => $evento->valoresAnteriores,
                'valores_nuevos' => $evento->valoresNuevos,
                'ip' => $evento->ip,
                'fecha_hora' => $evento->fechaHora,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * Única fuente de verdad de visibilidad y filtros: la usan el listado y la exportación.
     * Quien no es superadmin queda acotado a su empresa e ignora cualquier `empresa_id` enviado.
     */
    public function consultaVisible(Visibilidad $visibilidad, FiltrosBitacora $filtros, ?int $hastaId = null): Builder
    {
        $consulta = BitacoraAuditoria::query()
            ->when($hastaId !== null, fn (Builder $q) => $q->where('id', '<=', $hastaId));

        if ($visibilidad->todasLasEmpresas) {
            $consulta->when($filtros->empresaId !== null, fn (Builder $q) => $q->where('empresa_id', $filtros->empresaId));
        } else {
            $consulta->where('empresa_id', $visibilidad->empresaId);
        }

        return $consulta
            // Formato explícito con milisegundos: al enlazar un Carbon, Laravel los trunca y dejaría fuera el último segundo del día.
            ->when($filtros->desde, fn (Builder $q, $desde) => $q->where('fecha_hora', '>=', $desde->format('Y-m-d H:i:s.v')))
            ->when($filtros->hasta, fn (Builder $q, $hasta) => $q->where('fecha_hora', '<=', $hasta->format('Y-m-d H:i:s.v')))
            ->when($filtros->modulo, fn (Builder $q, $modulo) => $q->where('modulo', $modulo))
            ->when($filtros->accion, fn (Builder $q, $accion) => $q->where('accion', $accion->value))
            ->when($filtros->entidad, fn (Builder $q, $entidad) => $q->where('entidad', $entidad))
            ->when($filtros->autor, function (Builder $q, string $autor) {
                $patron = '%'.addcslashes($autor, '%_\\').'%';

                $q->where(fn (Builder $q) => $q->where('autor_nombre', 'like', $patron)->orWhere('autor_usuario', 'like', $patron));
            })
            ->when($filtros->autorUsuarioId, fn (Builder $q, int $id) => $q->where('usuario_id', $id))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id');
    }

    public function paginarVisibles(Visibilidad $visibilidad, FiltrosBitacora $filtros, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->consultaVisible($visibilidad, $filtros)->paginate($porPagina, page: $pagina);
    }

    public function contarVisibles(Visibilidad $visibilidad, FiltrosBitacora $filtros, ?int $hastaId = null): int
    {
        return $this->consultaVisible($visibilidad, $filtros, $hastaId)->reorder()->count();
    }

    /** Punto de corte de una exportación: lo insertado después no entra en el archivo. */
    public function ultimoId(): int
    {
        return (int) BitacoraAuditoria::query()->max('id');
    }

    /** @return LazyCollection<int, BitacoraAuditoria> */
    public function filasVisibles(Visibilidad $visibilidad, FiltrosBitacora $filtros, ?int $hastaId = null): LazyCollection
    {
        return $this->consultaVisible($visibilidad, $filtros, $hastaId)->lazy(1000);
    }

    /** @param list<string> $eventoIds  @return list<string> los que ya están insertados */
    public function eventoIdsExistentes(array $eventoIds): array
    {
        return BitacoraAuditoria::query()->whereIn('evento_id', $eventoIds)->pluck('evento_id')->all();
    }
}
