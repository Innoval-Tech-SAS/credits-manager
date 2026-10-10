<?php

namespace App\Facturacion\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Core\Dinero\Dinero;
use App\Facturacion\Data\ParametrosFacturacion;
use App\Facturacion\Repositories\ConfiguracionFacturacionRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Facades\DB;

class ConfiguracionFacturacionService
{
    public function __construct(
        protected ConfiguracionFacturacionRepository $configuraciones,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function parametros(int $empresaId): ParametrosFacturacion
    {
        return ParametrosFacturacion::desde($this->configuraciones->valoresDeEmpresa($empresaId));
    }

    /**
     * Guarda los parámetros recibidos (ya validados por tipo y rango). Solo se escribe y audita lo que cambia,
     * con el valor anterior y el nuevo.
     *
     * @param  array{nota_debito_tope: string}  $datos  montos en pesos, como llegan del request
     */
    public function guardar(Usuario $usuario, array $datos): ParametrosFacturacion
    {
        $empresaId = (int) $usuario->empresa_id;

        return DB::transaction(function () use ($usuario, $empresaId, $datos) {
            $actuales = $this->parametros($empresaId)->aFilas();
            $nuevos = ParametrosFacturacion::desde([
                'nota_debito_tope' => (string) Dinero::desdePesos($datos['nota_debito_tope'])->centavos(),
            ]);
            $filasNuevas = $nuevos->aFilas();

            $cambiados = array_filter(
                $filasNuevas,
                fn (string $valor, string $clave) => $valor !== $actuales[$clave],
                ARRAY_FILTER_USE_BOTH,
            );

            if ($cambiados === []) {
                return $nuevos;
            }

            foreach ($cambiados as $clave => $valor) {
                $this->configuraciones->guardar($empresaId, $clave, $valor, $usuario->id);
            }

            $this->auditoria->registrar(
                AccionAuditoria::Modificar,
                AuditoriaConstants::MODULO_FACTURACION,
                'Parámetros de facturación modificados: '.implode(', ', array_keys($cambiados)),
                'facturacion_configuracion',
                null,
                array_intersect_key($actuales, $cambiados),
                $cambiados,
                autor: $usuario,
            );

            return $nuevos;
        });
    }
}
