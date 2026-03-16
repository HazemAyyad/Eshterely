<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardMetricsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected AdminDashboardMetricsService $metricsService
    ) {
    }

    public function index(): View
    {
        $data = $this->metricsService->getDashboardData();

        return view('admin.dashboard', $data);
    }
}
