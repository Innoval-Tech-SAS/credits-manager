<?php

namespace App\Respaldos\Controllers;

use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Http\Controllers\Controller;
use App\Monitoreo\Services\EstadoRespaldosService;
use App\Respaldos\Services\RespaldoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Throwable;

/** Detalle de respaldos para la pantalla del superadmin: mismo `EstadoRespaldosService` que ya usa `/monitoreo/respaldos`. */
class ResumenRespaldosController extends Controller
{
    public function __construct(
        protected EstadoRespaldosService $estado,
        protected RespaldoService $respaldos,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->estado->resumen(CarbonImmutable::now()));
    }

    /** Respaldo manual bajo demanda: solo la clase `manual`, síncrono (sin worker nuevo, ver el plan). */
    public function ejecutar(): JsonResponse
    {
        try {
            $this->respaldos->ejecutar(CarbonImmutable::now(config('app.timezone')), manual: true);
        } catch (Throwable $e) {
            return response()->json(['message' => 'El respaldo falló: '.$e->getMessage()], 500);
        }

        $this->auditoria->registrar(AccionAuditoria::Crear, 'respaldos', 'Respaldo manual ejecutado por el superadmin');

        return response()->json(['message' => 'Respaldo creado correctamente.']);
    }
}
