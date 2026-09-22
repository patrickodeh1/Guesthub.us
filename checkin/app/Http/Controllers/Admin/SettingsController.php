<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\MediaService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', [
            'settings' => $this->generalSettings(),
        ]);
    }

    public function legalEdit()
    {
        return view('admin.settings-legal', [
            'settings' => $this->legalSettings(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'gps_radius_meters' => ['required', 'integer', 'min:25', 'max:5000'],
            'site_logo' => ['nullable', 'image', 'max:2048'],
            'existing_site_logo' => ['nullable', 'string'],
            'favicon' => ['nullable', 'image', 'mimes:ico,png,jpg,jpeg,svg', 'max:512'],
            'existing_favicon' => ['nullable', 'string'],
            'brand_color' => ['required', 'string', 'max:20'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'default_intro' => ['nullable', 'string'],
            'gps_verify_message' => ['nullable', 'string', 'max:500'],
            'lock_message' => ['nullable', 'string', 'max:500'],
            'background_check_step_instructions' => ['nullable', 'string', 'max:1000'],
            'airbnb_payment_instructions' => ['nullable', 'string', 'max:1000'],
            'arrival_disclaimer' => ['nullable', 'string', 'max:2000'],
            'default_deposit_cap_dollars' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'processing_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($request->hasFile('site_logo')) {
            $file = $request->file('site_logo');
            $data['site_logo'] = $file->store('brand', 'public');
            MediaService::register($data['site_logo'], $file->getClientOriginalName(), $file->getSize(), 'Settings');
        } elseif ($request->filled('existing_site_logo')) {
            $data['site_logo'] = $request->input('existing_site_logo');
        } else {
            unset($data['site_logo']);
        }
        unset($data['existing_site_logo']);

        if ($request->hasFile('favicon')) {
            $file = $request->file('favicon');
            $data['favicon'] = $file->store('brand', 'public');
            MediaService::register($data['favicon'], $file->getClientOriginalName(), $file->getSize(), 'Settings');
        } elseif ($request->filled('existing_favicon')) {
            $data['favicon'] = $request->input('existing_favicon');
        } else {
            unset($data['favicon']);
        }
        unset($data['existing_favicon']);

        if (array_key_exists('default_deposit_cap_dollars', $data)) {
            Setting::putValue('default_deposit_cap_cents', (int) round((float) ($data['default_deposit_cap_dollars'] ?? 0) * 100));
            unset($data['default_deposit_cap_dollars']);
        }

        if (array_key_exists('processing_fee_percent', $data)) {
            Setting::putValue('processing_fee_percent', (string) ($data['processing_fee_percent'] ?? 0));
            unset($data['processing_fee_percent']);
        }

        foreach ($data as $key => $value) {
            Setting::putValue($key, $value);
        }
        ActivityLog::record('settings_updated', 'Brand and system settings were updated.', 'settings');

        return back()->with('success', 'Settings saved.');
    }

    public function legalUpdate(Request $request)
    {
        $data = $request->validate([
            'site_copyright' => ['nullable', 'string', 'max:255'],
            'legal_terms_content' => ['nullable', 'string'],
            'legal_privacy_content' => ['nullable', 'string'],
            'legal_rental_contract_content' => ['nullable', 'string'],
            'legal_sms_consent_content' => ['nullable', 'string'],
            'legal_effective_date' => ['nullable', 'string', 'max:255'],
            'terms_page_title' => ['nullable', 'string', 'max:255'],
            'privacy_page_title' => ['nullable', 'string', 'max:255'],
            'rental_contract_page_title' => ['nullable', 'string', 'max:255'],
            'terms_url' => ['nullable', 'string', 'max:255'],
            'privacy_url' => ['nullable', 'string', 'max:255'],
        ]);

        // Bump the relevant version counter whenever its content actually
        // changes, mirroring the rental_contract_version pattern. This is
        // what makes each SmsConsentEvent's stored version meaningful: a
        // guest's consent record can be tied back to the exact text they
        // saw, even after the admin edits it later.
        $this->bumpVersionIfChanged($data, 'legal_terms_content', 'terms_version');
        $this->bumpVersionIfChanged($data, 'legal_privacy_content', 'privacy_policy_version');
        $this->bumpVersionIfChanged($data, 'legal_rental_contract_content', 'rental_contract_version');
        $this->bumpVersionIfChanged($data, 'legal_sms_consent_content', 'sms_consent_version');

        foreach ($data as $key => $value) {
            Setting::putValue($key, $value);
        }

        ActivityLog::record('settings_updated', 'Legal and privacy page settings were updated.', 'settings');

        return back()->with('success', 'Legal settings saved.');
    }

    protected function bumpVersionIfChanged(array $data, string $contentKey, string $versionKey): void
    {
        if (! array_key_exists($contentKey, $data)) {
            return;
        }

        $previous = Setting::getValue($contentKey, '');
        if ($data[$contentKey] === $previous) {
            return;
        }

        $current = (int) Setting::getValue($versionKey, '1');
        Setting::putValue($versionKey, (string) ($current + 1));
    }

    protected function generalSettings(): array
    {
        return [
            'gps_radius_meters' => Setting::getValue('gps_radius_meters', 150),
            'site_logo' => Setting::getValue('site_logo'),
            'favicon' => Setting::getValue('favicon'),
            'brand_color' => Setting::getValue('brand_color', '#0f766e'),
            'contact_phone' => Setting::getValue('contact_phone', '+1 555 123 4567'),
            'contact_email' => Setting::getValue('contact_email', 'guestservices@example.com'),
            'default_intro' => Setting::getValue('default_intro', 'Your arrival details and local guide are ready when you are.'),
            'gps_verify_message' => Setting::getValue('gps_verify_message', "It's Go Time!"),
            'lock_message' => Setting::getValue('lock_message', "If you'd like quicker access to the unit, you can download the August Home app."),
            'background_check_step_name' => Setting::getValue('background_check_step_name', 'Background Check'),
            'background_check_step_instructions' => Setting::getValue('background_check_step_instructions', 'Please be on the lookout for an email from Airbnb so that you can submit the required hold for incidentals. This hold is refunded after checkout.'),
            'arrival_disclaimer' => Setting::getValue('arrival_disclaimer', "<p><strong>Before you start navigating to the property, please read your arrival instructions.</strong> Guests who arrive without reading them often end up waiting outside and messaging us for entry. Please go through every step first, then type <strong>agree</strong> below to continue.</p>"),
            'airbnb_payment_instructions' => Setting::getValue('airbnb_payment_instructions', "We'll send you a payment request through [[platform]]. Once it's completed, we'll confirm and send your check-in details."),
            'default_deposit_cap_dollars' => Setting::getValue('default_deposit_cap_cents', 0) / 100,
            'processing_fee_percent' => Setting::getValue('processing_fee_percent', 0),
        ];
    }

    protected function legalSettings(): array
    {
        return [
            'site_copyright' => Setting::getValue('site_copyright', '© Dreamzone Media LLC d/b/a Guest Hub'),
            'legal_effective_date' => Setting::getValue('legal_effective_date', date('F j, Y')),
            'terms_page_title' => Setting::getValue('terms_page_title', 'Terms of Service'),
            'privacy_page_title' => Setting::getValue('privacy_page_title', 'Privacy Policy'),
            'rental_contract_page_title' => Setting::getValue('rental_contract_page_title', 'Rental Contract'),
            'terms_url' => Setting::getValue('terms_url', '/terms'),
            'privacy_url' => Setting::getValue('privacy_url', '/privacy-policy'),
            'legal_terms_content' => Setting::getValue('legal_terms_content', '<p>Update your Terms of Service here.</p>'),
            'legal_privacy_content' => Setting::getValue('legal_privacy_content', '<p>Update your Privacy Policy here.</p>'),
            'legal_rental_contract_content' => Setting::getValue('legal_rental_contract_content', '<p>Update your Rental Contract here.</p>'),
            'legal_sms_consent_content' => Setting::getValue('legal_sms_consent_content', '<p>Update your SMS consent disclosure here.</p>'),
        ];
    }
}
