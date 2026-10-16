<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;

class PropertyLockboxController extends Controller
{
    public function update(Request $request, Property $property)
    {
        $data = $request->validate(['lockbox_code' => ['nullable', 'string', 'max:255']]);

        // The Property model already logs who changed the code (never the code itself).
        $property->forceFill(['lockbox_code' => trim((string) ($data['lockbox_code'] ?? '')) ?: null])->save();

        return response()->json(['ok' => true, 'lockbox_code' => $property->lockbox_code]);
    }
}
