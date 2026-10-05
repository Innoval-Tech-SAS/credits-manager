<?php

namespace App\Metas\Services;

use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Metas\Constants\MetasConstants;
use App\Metas\Data\ParametrosMetas;
use App\Metas\Repositories\ConfiguracionMetaRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Facades\DB;

class ConfiguracionMetasService
{
    public function __construct(
        protected ConfiguracionMetaRepository $configuraciones,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function parametros(int $empresaId): ParametrosMetas
    {
        return ParametrosMetas::desde($this->configuraciones->valoresDeEmpresa($empresaId));
    }

    /**
     * Guarda los parámetros recibidos (ya validados por tipo y rango). Solo se escribe y audita lo que cambia,
     * con el valor anterior y el nuevo. Nunca toca períodos ya cerrados: solo se leen al calcular.
     *
     * @param  array<string, bool|int|string>  $datos
     */
    public function guardar(Usuario $usuario, array $datos): ParametrosMetas
    {
        $empresaId = (int) $usuario->empresa_id;

        return DB::transaction(function () use ($usuario, $empresaId, $datos) {
            $actuales = $this->parametros($empresaId)->aFilas();
            $nuevos = ParametrosMetas::desde(array_map(
                fn (mixed $valor): string => is_bool($valor) ? ($valor ? '1' : '0') : (string) $valor,
                $datos,
            ));
            $filasNuevas = $nuevos->aFilas();

            $cambiados = array_filter(
                array_intersect_key($filasNuevas, $datos),
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
                MetasConstants::MODULO,
                'Parámetros de metas modificados: '.implode(', ', array_keys($cambiados)),
                'metas_configuracion',
                null,
                array_intersect_key($actuales, $cambiados),
                $cambiados,
            );

            return $nuevos;
        });
    }
}
