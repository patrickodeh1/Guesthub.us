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
        $notices = GuestNotice::with('property')
            ->when(! auth()->user()->hasRole('admin'), fn ($q) => $q->whereIn('property_id', Property::visibleTo(auth()->user())->select('properties.id')))
            ->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.notices.index', [
            'notices' => $notices,
        ]);
    }

    public function create()
    {
        return view('admin.notices.form', [
            'notice' => new GuestNotice(['active' => true, 'once_per_booking' => true, 'type' => 'popup', 'phase' => 'checkin', 'day_scope' => 'any']),
            'properties' => Property::visibleTo(auth()->user())->orderBy('name')->get(),
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
        $this->authorizeNotice($notice);
        return view('admin.notices.form', [
            'notice' => $notice,
            'properties' => Property::visibleTo(auth()->user())->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, GuestNotice $notice)
    {
        $this->authorizeNotice($notice);
        $notice->update($this->validated($request));

        ActivityLogService::admin('guest_notice_updated', auth()->user()->name." updated a guest notice: {$notice->title}.", 'settings', [
            'metadata' => ['notice_id' => $notice->id],
        ]);

        return redirect()->route('admin.notices.index')->with('success', 'Guest notice updated.');
    }

    public function destroy(GuestNotice $notice)
    {
        $this->authorizeNotice($notice);
        $title = $notice->title;
        $notice->delete();

        ActivityLogService::admin('guest_notice_deleted', auth()->user()->name." deleted a guest notice: {$title}.", 'settings');

        return redirect()->route('admin.notices.index')->with('success', 'Guest notice deleted.');
    }

    private function authorizeNotice(GuestNotice $notice): void
    {
        $user = auth()->user();
        abort_unless(
            $user->hasRole('admin')
            || ($notice->property_id && Property::visibleTo($user)->whereKey($notice->property_id)->exists()),
            403,
            'You do not have access to this notice.'
        );
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

        if (empty($data['property_id']) && ! auth()->user()->hasRole('admin')) {
            abort(403, 'Only administrators can create notices that apply to every property.');
        }

        if (! empty($data['property_id']) && ! auth()->user()->hasRole('admin')
            && ! Property::visibleTo(auth()->user())->whereKey($data['property_id'])->exists()) {
            abort(403, 'You cannot create notices for that property.');
        }

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
