<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EarlyAccessLead;

class EarlyAccessLeadController extends Controller
{
    public function index()
    {
        $leads = EarlyAccessLead::latest()->paginate(25);

        return view('admin.early-access-leads.index', compact('leads'));
    }

    public function markContacted(EarlyAccessLead $lead)
    {
        $lead->update(['contacted_at' => now()]);

        return back()->with('success', 'Marked as contacted.');
    }
}
