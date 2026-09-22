<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuestNotice;
use App\Models\Property;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class GuestNoticeController extends Controller
{
    public function index()
    {
        $notices = GuestNotice::with('property')->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.notices.index', [
            'notices' => $notices,
        ]);
    }

    public function create()
    {
        return view('admin.notices.form', [
            'notice' => new GuestNotice(['active' => true, 'once_per_booking' => true, 'type' => 'popup', 'phase' => 'checkin', 'day_scope' => 'any']),
            'properties' => Property::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $notice = GuestNotice::create($data);

        ActivityLogService::admin('guest_notice_created', auth()->user()->name." created a guest notice: {$notice->title}.", 'settings', [
            'metadata' => ['notice_id' => $notice->id],
        ]);

        return redirect()->route('admin.notices.index')->with('success', 'Guest notice created.');
    }

    public function edit(GuestNotice $notice)
    {
        return view('admin.notices.form', [
            'notice' => $notice,
            'properties' => Property::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, GuestNotice $notice)
    {
        $notice->update($this->validated($request));

        ActivityLogService::admin('guest_notice_updated', auth()->user()->name." updated a guest notice: {$notice->title}.", 'settings', [
            'metadata' => ['notice_id' => $notice->id],
        ]);

        return redirect()->route('admin.notices.index')->with('success', 'Guest notice updated.');
    }

    public function destroy(GuestNotice $notice)
    {
        $title = $notice->title;
        $notice->delete();

        ActivityLogService::admin('guest_notice_deleted', auth()->user()->name." deleted a guest notice: {$title}.", 'settings');

        return redirect()->route('admin.notices.index')->with('success', 'Guest notice deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'type' => ['required', 'in:popup,step'],
            'phase' => ['required', 'in:any,checkin,checkout,guide'],
            'day_scope' => ['required', 'in:any,arrival_day,checkout_day'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'requires_parking' => ['nullable', 'in:any,yes,no'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['once_per_booking'] = $request->boolean('once_per_booking');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $data['requires_parking'] = match ($data['requires_parking'] ?? 'any') {
            'yes' => true,
            'no' => false,
            default => null,
        };

        return $data;
    }
}
