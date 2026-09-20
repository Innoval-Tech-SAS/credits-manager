<?php

namespace App\Dashboard\Controllers;

use App\Dashboard\Services\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function index(): JsonResponse
    {
        return response()->json($this->dashboard->resumen());
    }
}
