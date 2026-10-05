<?php

namespace App\Facturacion\Controllers;

use App\Facturacion\Enums\TipoDocumentoFacturacion;
use App\Facturacion\Requests\ConfigurarConsecutivoRequest;
use App\Facturacion\Services\ConsecutivoFacturacionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsecutivoFacturacionController extends Controller
{
    public function __construct(protected ConsecutivoFacturacionService $consecutivos) {}

    public function index(Request $peticion): JsonResponse
    {
        return response()->json(['consecutivos' => $this->consecutivos->listar($peticion->user())]);
    }

    public function update(ConfigurarConsecutivoRequest $peticion, TipoDocumentoFacturacion $tipo): JsonResponse
    {
        $this->consecutivos->configurar(
            $tipo,
            $peticion->user(),
            $peticion->validated('prefijo'),
            $peticion->validated('numero_inicial') !== null ? (int) $peticion->validated('numero_inicial') : null,
        );

        return response()->json(['consecutivos' => $this->consecutivos->listar($peticion->user())]);
    }
}
