<?php

namespace App\Dashboard\Controllers;

use App\Dashboard\Services\PanelSuperadminService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** Home del superadmin: solo superadmin (middleware de ruta). */
class PanelSuperadminController extends Controller
{
    public function __construct(protected PanelSuperadminService $panel) {}

    public function index(): JsonResponse
    {
        return response()->json($this->panel->resumen());
    }
}
