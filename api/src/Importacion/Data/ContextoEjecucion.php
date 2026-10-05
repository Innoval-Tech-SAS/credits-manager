<?php

namespace App\Importacion\Data;

use App\Core\Dinero\Dinero;
use App\Creditos\Models\Cuota;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;

/**
 * Estado de una pasada (validación o escritura) sobre el archivo. Lo arma el worker con `empresa_id` explícito y lo
 * consumen los procesadores; nunca viaja en la cola. En validación `escribir` es false y ningún procesador escribe.
 */
final class ContextoEjecucion
{
    /** @var list<array{severidad: string, columna: ?string, motivo: string}> */
    public array $reportes = [];

    /** @var array<string, int> nombre normalizado → id de empleador */
    public array $empleadoresPorNombre = [];

    /** @var array<string, string> nombre normalizado → nombre tal como aparece en el archivo, de los empleadores que faltan */
    public array $empleadoresFaltantes = [];

    /** @var array<string, int> cédula → id de cliente, precargado por lote */
    public array $clientesPorCedula = [];

    /** @var array<string, int> código de crédito → id, precargado por lote */
    public array $creditosPorCodigo = [];

    /** @var array<string, int> cuenta PUC → id de cuenta, precargado por lote */
    public array $cuentasPorCodigo = [];

    /** @var array<string, int> nombre normalizado del usuario → id, para «Registrado por» */
    public array $usuariosPorNombre = [];

    /** @var array<int, Collection<int, Cuota>> crédito → plan, cargado una vez; en validación los pagos lo avanzan en memoria */
    public array $planes = [];

    /** @var array<string, true> claves ya vistas en el archivo, para detectar repetidos */
    public array $vistos = [];

    public Dinero $asientoAcumulado;

    public function __construct(
        public readonly int $empresaId,
        public readonly Usuario $usuario,
        public readonly bool $escribir,
        public ?bool $crearEmpleadores,
        public bool $generarAsiento,
        public readonly ?int $cuentaContrapartidaId,
    ) {
        $this->asientoAcumulado = Dinero::cero();
    }

    /** Devuelve true la primera vez que ve la clave; false si ya apareció antes en el archivo. */
    public function primeraVez(string $clave): bool
    {
        if (isset($this->vistos[$clave])) {
            return false;
        }

        $this->vistos[$clave] = true;

        return true;
    }
}
