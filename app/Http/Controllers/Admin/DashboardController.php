<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'users' => DB::table('users')->count(),
            'orders' => DB::table('orders')->count(),
            'support_open' => DB::table('support_tickets')->where('status', '!=', 'resolved')->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
