<?php

namespace App\Dashboard\Services;

use App\Auditoria\Enums\AccionAuditoria;
use App\Dashboard\Repositories\PanelSuperadminRepository;
use App\Empresas\Models\Empresa;
use App\Monitoreo\Repositories\EjecucionTareaProgramadaRepository;
use App\Monitoreo\Services\EstadoRespaldosService;
use App\Monitoreo\Services\EstadoServiciosService;
use Carbon\CarbonImmutable;

/** Resumen del home del superadmin: las 6 cifras de plataforma más la tendencia de accesos y las altas recientes. */
class PanelSuperadminService
{
    private const DIAS_TENDENCIA = 14;

    private const EMPRESAS_RECIENTES = 5;

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
            'tendencia_logins' => $this->tendenciaLogins(),
            'empresas_recientes' => $this->repositorio->empresasRecientes(self::EMPRESAS_RECIENTES)
                ->map(fn (Empresa $empresa) => [
                    'id' => $empresa->id,
                    'nombre_empresa' => $empresa->nombre_empresa,
                    'creado_en' => $empresa->creado_en,
                ])
                ->all(),
        ];
    }

    /** @return array<int, array{fecha: string, exitosos: int, fallidos: int}> un punto por día, sin huecos, últimos `DIAS_TENDENCIA` días. */
    private function tendenciaLogins(): array
    {
        $desde = CarbonImmutable::now()->subDays(self::DIAS_TENDENCIA - 1)->startOfDay();
        $porFecha = $this->repositorio->tendenciaLoginsPorDia($desde)->groupBy('fecha');

        return collect(range(0, self::DIAS_TENDENCIA - 1))
            ->map(function (int $offset) use ($desde, $porFecha) {
                $fecha = $desde->addDays($offset)->toDateString();
                $delDia = $porFecha->get($fecha, collect());

                $exitosos = $delDia->firstWhere('accion', AccionAuditoria::Login->value);
                $fallidos = $delDia->firstWhere('accion', AccionAuditoria::LoginFallido->value);

                return [
                    'fecha' => $fecha,
                    'exitosos' => $exitosos ? (int) $exitosos->total : 0,
                    'fallidos' => $fallidos ? (int) $fallidos->total : 0,
                ];
            })
            ->all();
    }
}
