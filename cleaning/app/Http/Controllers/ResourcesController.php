<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResourcesController extends Controller
{
    /**
     * Get published instructional videos for a specific property.
     */
    public function getPropertyVideos(Request $request)
    {
        $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'category' => ['nullable', 'string', 'in:general,room-specific,equipment,safety,process'],
        ]);

        $user = $request->user();
        $propertyId = (int) $request->query('property_id');
        $category = $request->query('category');

        // Verify that the authenticated user has access to this property
        $hasAccess = false;
        if ($user->hasRole('admin')) {
            $hasAccess = true;
        } else {
            $property = Property::find($propertyId);
            if ($property) {
                if ($user->hasRole('owner') && $property->owner_id === $user->id) {
                    $hasAccess = true;
                } elseif ($user->hasRole('company')) {
                    $hasAccess = Property::where('id', $propertyId)
                        ->where(function ($q) use ($user) {
                            $q->where('owner_id', $user->id)
                                ->orWhereIn('owner_id', function ($sub) use ($user) {
                                    $sub->select('id')->from('users')->where('owner_id', $user->id);
                                })
                                ->orWhereIn('owner_id', function ($sub) use ($user) {
                                    $sub->select('owner_id')
                                        ->from('housekeeper_owner')
                                        ->where('housekeeper_id', $user->id);
                                });
                        })->exists();
                } else {
                    // Housekeeper check
                    $isAssignedUser = DB::table('property_user')
                        ->where('property_id', $propertyId)
                        ->where('user_id', $user->id)
                        ->exists();

                    $isAssignedSession = DB::table('cleaning_sessions')
                        ->where('property_id', $propertyId)
                        ->where('housekeeper_id', $user->id)
                        ->exists();

                    if ($isAssignedUser || $isAssignedSession) {
                        $hasAccess = true;
                    }
                }
            }
        }

        if (!$hasAccess) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $property = Property::findOrFail($propertyId);

        $videosQuery = $property->instructionalVideos()
            ->published()
            ->when($category, function ($q) use ($category) {
                $q->where('category', $category);
            });

        $videos = $videosQuery->get();

        return response()->json($videos);
    }
}
