<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GuestPortalDashboardData;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GuestPortalDashboardData $data)
    {
        return view('admin.dashboard', $data->get($request->user()));
    }
}
