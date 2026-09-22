<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyLock;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class PropertyLockController extends Controller
{
    public function store(Request $request, Property $property)
    {
        $data = $request->validate([
            'seam_device_id' => ['required', 'string', 'max:255', 'unique:property_locks,seam_device_id'],
            'label' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
        ], [
            'seam_device_id.unique' => 'This lock (device ID) is already assigned to another property.',
        ]);

        $data['label'] = $data['label'] ?: 'Front Door';

        $lock = $property->locks()->create($data);

        ActivityLogService::admin('lock_added', request()->user()->name." added a lock ({$lock->label}) to {$property->name}.", 'properties', [
            'metadata' => ['property_id' => $property->id, 'lock_id' => $lock->id],
        ]);

        return back()->with('success', 'Lock added.');
    }

    public function update(Request $request, Property $property, PropertyLock $lock)
    {
        abort_unless($lock->property_id === $property->id, 404);

        $data = $request->validate([
            'seam_device_id' => ['required', 'string', 'max:255', 'unique:property_locks,seam_device_id,'.$lock->id],
            'label' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
        ], [
            'seam_device_id.unique' => 'This lock (device ID) is already assigned to another property.',
        ]);

        $data['label'] = $data['label'] ?: 'Front Door';

        $lock->update($data);

        ActivityLogService::admin('lock_updated', request()->user()->name." updated a lock ({$lock->label}) on {$property->name}.", 'properties', [
            'metadata' => ['property_id' => $property->id, 'lock_id' => $lock->id],
        ]);

        return back()->with('success', 'Lock updated.');
    }

    public function destroy(Property $property, PropertyLock $lock)
    {
        abort_unless($lock->property_id === $property->id, 404);

        $label = $lock->label;
        $lock->delete();

        ActivityLogService::admin('lock_removed', request()->user()->name." removed a lock ({$label}) from {$property->name}.", 'properties', [
            'metadata' => ['property_id' => $property->id],
        ]);

        return back()->with('success', 'Lock removed.');
    }
}
