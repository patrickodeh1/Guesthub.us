<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\SmsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LandingController extends Controller
{
    public function show()
    {
        return view('landing', [
            'siteLogo' => Setting::getValue('site_logo'),
        ]);
    }
}
