<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyLock;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class PropertyLockController extends Controller
{
    public function store(Request $request, Property $property, SeamService $seam)
    {
        $user = $request->user();
        $data = $request->validate([
            'seam_device_id' => ['required', 'string', 'max:255', 'unique:property_locks,seam_device_id'],
            'label' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
        ], [
            'seam_device_id.unique' => 'This lock is already assigned to another property.',
        ]);

        if (! $user->hasRole('admin')) {
            $device = $property->owner
                ? $seam->findDeviceForHost($property->owner, $data['seam_device_id'])
                : null;
            abort_unless($device, 403, 'Choose a lock connected through your own Yale or August account.');

            $data['label'] = $data['label'] ?: ($device['display_name'] ?? 'Smart lock');
            $data['manufacturer'] = $device['device_provider']['device_provider_name']
                ?? $device['device_provider']['display_name']
                ?? $property->owner?->seamConnectedAccounts()->where('status', 'connected')->value('provider');
        }

        $lock = $property->locks()->create($data);

        ActivityLogService::admin('lock_added', $user->name." added a lock ({$lock->label}) to {$property->name}.", 'properties', [
            'metadata' => ['property_id' => $property->id, 'lock_id' => $lock->id],
        ]);

        return back()->with('success', 'Lock added.');
    }

    public function update(Request $request, Property $property, PropertyLock $lock, SeamService $seam)
    {
        abort_unless($lock->property_id === $property->id, 404);

        $user = $request->user();
        $data = $request->validate([
            'seam_device_id' => ['required', 'string', 'max:255', 'unique:property_locks,seam_device_id,'.$lock->id],
            'label' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
        ], [
            'seam_device_id.unique' => 'This lock is already assigned to another property.',
        ]);

        if (! $user->hasRole('admin')) {
            $device = $property->owner
                ? $seam->findDeviceForHost($property->owner, $data['seam_device_id'])
                : null;
            abort_unless($device, 403, 'Choose a lock connected through your own Yale or August account.');

            $data['label'] = $data['label'] ?: ($device['display_name'] ?? 'Smart lock');
            $data['manufacturer'] = $device['device_provider']['provider_name']
                ?? $device['device_provider']['provider']
                ?? $property->owner?->seamConnectedAccounts()->where('status', 'connected')->value('provider');
        }

        $lock->update($data);

        ActivityLogService::admin('lock_updated', $user->name." updated a lock ({$lock->label}) on {$property->name}.", 'properties', [
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
