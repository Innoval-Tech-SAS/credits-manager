<?php

namespace App\Dashboard\Services;

use App\Dashboard\Repositories\PanelSuperadminRepository;
use App\Monitoreo\Repositories\EjecucionTareaProgramadaRepository;
use App\Monitoreo\Services\EstadoRespaldosService;
use App\Monitoreo\Services\EstadoServiciosService;
use Carbon\CarbonImmutable;

/** Las 6 cifras del home del superadmin: todas de plataforma, ninguna de negocio. */
class PanelSuperadminService
{
    public function __construct(
        protected PanelSuperadminRepository $repositorio,
        protected EstadoServiciosService $servicios,
        protected EstadoRespaldosService $respaldos,
        protected EjecucionTareaProgramadaRepository $ejecucionesTareas,
    ) {}

    public function resumen(): array
    {
        $servicios = $this->servicios->revisar();
        $respaldos = $this->respaldos->resumen(CarbonImmutable::now());

        return [
            'logins_fallidos_7_dias' => $this->repositorio->contarLoginsFallidosDesde(now()->subDays(7)),
            'usuarios_activos' => $this->repositorio->contarUsuariosActivos(),
            'empresas_activas' => $this->repositorio->contarEmpresasActivas(),
            'servicios_caidos' => collect($servicios)->where('estado', 'caido')->count(),
            'ultimo_respaldo' => [
                'horas_desde_ultimo_diario' => $respaldos['horas_desde_ultimo_diario'],
                'alerta' => $respaldos['alerta'],
            ],
            'errores_tareas_mes' => $this->ejecucionesTareas->contarErroresDelMes(),
        ];
    }
}
