<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileTimezoneController extends Controller
{
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'timezone' => ['nullable', 'string', 'timezone:all'],
        ]);

        $request->user()->forceFill(['timezone' => $data['timezone'] ?: null])->save();

        if ($request->expectsJson()) {
            return response()->json(['timezone' => $request->user()->timezone]);
        }

        return back()->with('status', 'timezone-updated');
    }
}
