<?php

namespace App\Causacion\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Causacion\Models\ValorUvt;
use App\Causacion\Repositories\ValorUvtRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ValorUvtService
{
    public function __construct(
        protected ValorUvtRepository $valoresUvt,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(): Collection
    {
        return $this->valoresUvt->todos();
    }

    public function guardar(int $anio, float $valor): ValorUvt
    {
        $uvt = $this->valoresUvt->guardar($anio, $valor);

        $this->auditoria->registrarGuardado(AuditoriaConstants::MODULO_GASTOS, "Valor UVT del año {$anio} guardado", $uvt);

        return $uvt;
    }

    public function editar(ValorUvt $valorUvt, float $valor): ValorUvt
    {
        $this->exigirSinGastos($valorUvt);

        $anteriores = $this->auditoria->instantanea($valorUvt);
        $valorUvt = $this->valoresUvt->actualizar($valorUvt, ['valor' => $valor]);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_GASTOS, "Valor UVT del año {$valorUvt->anio} modificado", $valorUvt, $anteriores);

        return $valorUvt;
    }

    public function eliminar(ValorUvt $valorUvt): bool
    {
        $this->exigirSinGastos($valorUvt);

        $eliminado = $this->valoresUvt->eliminar($valorUvt);

        $this->auditoria->registrarEliminacion(AuditoriaConstants::MODULO_GASTOS, "Valor UVT del año {$valorUvt->anio} eliminado", $valorUvt);

        return $eliminado;
    }

    /** Si la empresa ya causó un gasto con fecha de ese año, el valor queda protegido. */
    private function exigirSinGastos(ValorUvt $valorUvt): void
    {
        if ($this->valoresUvt->tieneGastosEnAnio($valorUvt->anio)) {
            throw ValidationException::withMessages([
                'valor_uvt' => __('validation.custom.valor_uvt.tiene_gastos_asociados'),
            ]);
        }
    }
}
