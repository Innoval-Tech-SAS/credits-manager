<?php

namespace App\Usuarios\Controllers;

use App\Http\Controllers\Controller;
use App\Usuarios\Services\PermisoService;
use Illuminate\Http\JsonResponse;

class PermisoController extends Controller
{
    public function __construct(protected PermisoService $permisos) {}

    public function index(): JsonResponse
    {
        return response()->json(['permisos' => $this->permisos->listar()]);
    }
}
