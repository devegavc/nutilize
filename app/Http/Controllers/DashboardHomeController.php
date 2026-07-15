<?php

namespace App\Http\Controllers;

use App\Services\DashboardCacheService;
use Illuminate\Support\Facades\Auth;

class DashboardHomeController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (!$user || !$user->isPhysicalFacilitiesAdmin()) {
            return redirect('/dashboard/office/home')->with('error', 'Unauthorized access.');
        }

        // Use cached dashboard data
        $data = DashboardCacheService::getDashboardData(
            (int) $user->user_id,
            (int) ($user->office_id ?? 0)
        );

        return view('dashboard-home', $data);
    }

}