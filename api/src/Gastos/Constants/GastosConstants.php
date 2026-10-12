<?php

namespace App\Gastos\Constants;

/** Catálogos de gastos y tributario que otras operaciones consultan para elegir un valor (`algun_permiso`). */
class GastosConstants
{
    public const CONSULTA_TIPOS_GASTO = [
        'gastos.listar_tipos_gasto', 'gastos.crear_tipos_gasto', 'gastos.editar_tipos_gasto',
        'gastos.listar_parametrizaciones', 'gastos.crear_parametrizaciones', 'gastos.crear', 'contabilidad.crear_manual',
    ];

    public const CONSULTA_PARAMETRIZACIONES = ['gastos.listar_parametrizaciones', 'gastos.crear_parametrizaciones'];

    public const CONSULTA_ACTIVIDADES_ECONOMICAS = [
        'tributario.listar_actividades_economicas', 'tributario.crear_actividades_economicas',
        'tributario.editar_actividades_economicas', 'tributario.crear_tarifas_reteica', 'terceros.crear', 'terceros.editar',
    ];

    public const CONSULTA_TARIFAS_RETEICA = ['tributario.listar_tarifas_reteica', 'tributario.crear_tarifas_reteica'];

    public const CONSULTA_VALORES_UVT = ['tributario.listar_valores_uvt', 'tributario.editar_valores_uvt'];
}
