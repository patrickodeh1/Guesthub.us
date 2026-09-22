<?php

namespace App\Http\Controllers;

use App\Models\CleanerInstructionFamiliarity;
use App\Models\User;
use App\Models\Property;
use Illuminate\Http\Request;

class FamiliarityReportController extends Controller
{
    public function index(Request $request)
    {
        $authUser = $request->user();

        $query = CleanerInstructionFamiliarity::with(['user', 'task.properties']);

        // Filtering by Ownership
        if (!$authUser->hasRole('admin')) {
            if ($authUser->hasRole('company')) {
                // Get all housekeepers directly owned or owned by their owners
                $managedOwnerIds = User::where('owner_id', $authUser->id)
                    ->whereHas('roles', fn($q) => $q->where('name', 'owner'))
                    ->pluck('id');
                
                $housekeeperIds = User::whereHas('roles', fn($q) => $q->where('name', 'housekeeper'))
                    ->where(function($q) use ($authUser, $managedOwnerIds) {
                        $q->where('owner_id', $authUser->id)
                          ->orWhereIn('owner_id', $managedOwnerIds);
                    })
                    ->pluck('id');
                
                $query->whereIn('user_id', $housekeeperIds);
            } elseif ($authUser->hasRole('owner')) {
                // Get all housekeepers assigned to this owner
                $housekeeperIds = User::where('owner_id', $authUser->id)
                    ->whereHas('roles', fn($q) => $q->where('name', 'housekeeper'))
                    ->pluck('id');
                $query->whereIn('user_id', $housekeeperIds);
            }
        }

        if ($request->filled('cleaner_id')) {
            $query->where('user_id', $request->cleaner_id);
        }

        if ($request->filled('status')) {
            if ($request->status === 'familiar') {
                // Because required_instruction_views is a JSON field on User, we can't easily join on it.
                // We'll filter the collection later, but this is a tradeoff. 
                // Alternatively, assume a default of 3 in the query if we wanted raw SQL.
            }
        }

        $familiarities = $query->latest('last_viewed_at')->paginate(50)->withQueryString();

        // Calculate statuses
        foreach ($familiarities as $fam) {
            $required = $fam->user->preferences['required_instruction_views'] ?? 3;
            $fam->is_familiar = $fam->views_completed >= $required;
            $fam->required_views = $required;
            $fam->progress = min(100, ($fam->views_completed / $required) * 100);
        }

        // Apply in-memory status filter if requested
        if ($request->filled('status')) {
            $status = $request->status;
            $familiarities->setCollection(
                $familiarities->getCollection()->filter(function ($fam) use ($status) {
                    if ($status === 'familiar') return $fam->is_familiar;
                    if ($status === 'learning') return !$fam->is_familiar;
                    return true;
                })
            );
        }

        // Prepare dropdowns
        $cleanersQuery = User::whereHas('roles', fn($q) => $q->where('name', 'housekeeper'))->orderBy('name');
        
        if (!$authUser->hasRole('admin')) {
            if ($authUser->hasRole('company')) {
                $cleanersQuery->where(function($q) use ($authUser, $managedOwnerIds) {
                    $q->where('owner_id', $authUser->id)
                      ->orWhereIn('owner_id', $managedOwnerIds ?? []);
                });
            } elseif ($authUser->hasRole('owner')) {
                $cleanersQuery->where('owner_id', $authUser->id);
            }
        }
        $cleaners = $cleanersQuery->get();

        return view('reports.familiarity', compact('familiarities', 'cleaners'));
    }
}
