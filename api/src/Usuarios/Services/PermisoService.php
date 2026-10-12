<?php

namespace App\Usuarios\Services;

use App\Usuarios\Constants\PermisoConstants;
use App\Usuarios\Models\Permiso;
use App\Usuarios\Repositories\PermisoRepository;

class PermisoService
{
    public function __construct(protected PermisoRepository $permisos) {}

    /**
     * Catálogo vigente con sus nombres legibles, en el orden del menú (grupo, módulo y acción). Un permiso sin etiqueta
     * (no debería haberlo: lo impide un test) sale al final con su clave cruda en lugar de desaparecer del panel.
     *
     * @return list<array{id: int, modulo: string, accion: string, grupo: string, grupo_etiqueta: string, modulo_etiqueta: string, accion_etiqueta: string}>
     */
    public function listar(): array
    {
        $ordenAcciones = array_flip(array_keys(PermisoConstants::ACCIONES));
        $grupoDe = [];
        $ordenModulos = [];

        foreach (PermisoConstants::GRUPOS as $grupo => $definicion) {
            foreach ($definicion['modulos'] as $modulo) {
                $grupoDe[$modulo] = $grupo;
                $ordenModulos[$modulo] = count($ordenModulos);
            }
        }

        return $this->permisos->todosOrdenados()
            ->map(function (Permiso $permiso) use ($grupoDe) {
                $grupo = $grupoDe[$permiso->modulo] ?? $permiso->modulo;

                return [
                    'id' => $permiso->id,
                    'modulo' => $permiso->modulo,
                    'accion' => $permiso->accion,
                    'grupo' => $grupo,
                    'grupo_etiqueta' => PermisoConstants::GRUPOS[$grupo]['etiqueta'] ?? $grupo,
                    'modulo_etiqueta' => PermisoConstants::MODULOS[$permiso->modulo] ?? $permiso->modulo,
                    'accion_etiqueta' => PermisoConstants::ACCIONES["{$permiso->modulo}.{$permiso->accion}"] ?? $permiso->accion,
                ];
            })
            ->sortBy([
                fn (array $a, array $b) => ($ordenModulos[$a['modulo']] ?? PHP_INT_MAX) <=> ($ordenModulos[$b['modulo']] ?? PHP_INT_MAX),
                fn (array $a, array $b) => ($ordenAcciones["{$a['modulo']}.{$a['accion']}"] ?? PHP_INT_MAX)
                    <=> ($ordenAcciones["{$b['modulo']}.{$b['accion']}"] ?? PHP_INT_MAX),
            ])
            ->values()
            ->all();
    }
}
