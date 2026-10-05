<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\MediaService;
use App\Support\Branding;
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
            'site_name' => ['nullable', 'string', 'max:255'],
            'application_logo' => ['nullable', 'image', 'max:2048'],
            'application_icon' => ['nullable', 'image', 'max:2048'],
            'date_format' => ['nullable', 'string'],
            'time_format' => ['nullable', 'string', 'in:12,24'],
            'items_per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:255'],
            'auto_save_enabled' => ['nullable', 'boolean'],
            'auto_save_delay' => ['nullable', 'integer', 'min:100', 'max:5000'],
            'notify_session_started' => ['nullable', 'boolean'],
            'notify_session_completed' => ['nullable', 'boolean'],
            'notify_assignments' => ['nullable', 'boolean'],
            'notify_cleaning_started_global' => ['nullable', 'boolean'],
            'notify_cleaning_finished_global' => ['nullable', 'boolean'],
            'notify_photo_started_global' => ['nullable', 'boolean'],
            'notify_task_notes_global' => ['nullable', 'boolean'],
            'mandatory_instruction_viewing' => ['nullable', 'boolean'],
            'global_required_instruction_views' => ['nullable', 'integer', 'min:1', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'default_intro' => ['nullable', 'string'],
            'gps_verify_message' => ['nullable', 'string', 'max:500'],
            'lock_message' => ['nullable', 'string', 'max:500'],
            'background_check_step_instructions' => ['nullable', 'string', 'max:1000'],
            'airbnb_payment_instructions' => ['nullable', 'string', 'max:1000'],
            'default_deposit_cap_dollars' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'processing_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'site_copyright' => ['nullable', 'string', 'max:255'],
            'legal_effective_date' => ['nullable', 'string', 'max:255'],
            'terms_page_title' => ['nullable', 'string', 'max:255'],
            'privacy_page_title' => ['nullable', 'string', 'max:255'],
            'rental_contract_page_title' => ['nullable', 'string', 'max:255'],
            'terms_url' => ['nullable', 'string', 'max:255'],
            'privacy_url' => ['nullable', 'string', 'max:255'],
            'legal_terms_content' => ['nullable', 'string'],
            'legal_privacy_content' => ['nullable', 'string'],
            'legal_rental_contract_content' => ['nullable', 'string'],
            'legal_sms_consent_content' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('site_logo')) {
            $file = $request->file('site_logo');
            $data['application_logo_path'] = $file->store('brand', 'public');
            MediaService::register($data['application_logo_path'], $file->getClientOriginalName(), $file->getSize(), 'Settings');
        } elseif ($request->filled('existing_site_logo')) {
            $data['application_logo_path'] = $request->input('existing_site_logo');
        } else {
            unset($data['application_logo_path']);
        }
        unset($data['existing_site_logo']);

        if ($request->hasFile('favicon')) {
            $file = $request->file('favicon');
            $data['favicon_path'] = $file->store('brand', 'public');
            MediaService::register($data['favicon_path'], $file->getClientOriginalName(), $file->getSize(), 'Settings');
        } elseif ($request->filled('existing_favicon')) {
            $data['favicon_path'] = $request->input('existing_favicon');
        } else {
            unset($data['favicon_path']);
        }
        unset($data['existing_favicon']);

        if ($request->hasFile('application_logo')) {
            $file = $request->file('application_logo');
            $data['application_logo_path'] = $file->store('logos', 'public');
            MediaService::register($data['application_logo_path'], $file->getClientOriginalName(), $file->getSize(), 'Settings');
        }

        if ($request->hasFile('application_icon')) {
            $file = $request->file('application_icon');
            $data['application_icon_path'] = $file->store('logos', 'public');
            MediaService::register($data['application_icon_path'], $file->getClientOriginalName(), $file->getSize(), 'Settings');
        }

        if (array_key_exists('default_deposit_cap_dollars', $data)) {
            Setting::putValue('default_deposit_cap_cents', (int) round((float) ($data['default_deposit_cap_dollars'] ?? 0) * 100));
            unset($data['default_deposit_cap_dollars']);
        }

        if (array_key_exists('processing_fee_percent', $data)) {
            Setting::putValue('processing_fee_percent', (string) ($data['processing_fee_percent'] ?? 0));
            unset($data['processing_fee_percent']);
        }

        foreach ([
            'report_header_color', 'report_status_color', 'report_checklist_color',
            'report_issues_color', 'report_photos_color', 'report_time_color',
            'report_supplies_color', 'report_audit_color', 'report_button_primary_color',
            'report_button_secondary_color', 'site_copyright', 'legal_effective_date',
            'terms_page_title', 'privacy_page_title', 'rental_contract_page_title',
            'terms_url', 'privacy_url', 'legal_terms_content', 'legal_privacy_content',
            'legal_rental_contract_content', 'legal_sms_consent_content',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                if (str_ends_with($key, '_content')) {
                    $version = match ($key) {
                        'legal_terms_content' => 'terms_version',
                        'legal_privacy_content' => 'privacy_policy_version',
                        'legal_rental_contract_content' => 'rental_contract_version',
                        default => 'sms_consent_version',
                    };
                    $this->bumpVersionIfChanged($data, $key, $version);
                }
                Setting::putValue($key, $data[$key]);
                unset($data[$key]);
            }
        }


        if (array_key_exists('theme_color', $data) && $data['theme_color']) {
            $data['brand_color'] = $data['theme_color'];
        } else {
            unset($data['brand_color']);
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
            'site_name' => Setting::get('site_name', config('app.name', 'Guest Hub')),
            'theme_color' => Setting::get('theme_color', Branding::DEFAULT_THEME_COLOR),
            'button_primary_color' => Setting::get('button_primary_color', Branding::DEFAULT_BUTTON_COLOR),
            'button_success_color' => Setting::get('button_success_color', '#10b981'),
            'button_danger_color' => Setting::get('button_danger_color', '#ef4444'),
            'button_warning_color' => Setting::get('button_warning_color', '#f59e0b'),
            'button_info_color' => Setting::get('button_info_color', '#06b6d4'),
            'date_format' => Setting::get('date_format', 'M d, Y'),
            'time_format' => Setting::get('time_format', '12'),
            'items_per_page' => Setting::get('items_per_page', 15),
            'timezone' => Setting::get('timezone', config('app.timezone', 'UTC')),
            'site_logo' => Setting::get('application_logo_path') ?: Setting::getValue('site_logo'),
            'application_icon' => Setting::get('application_icon_path'),
            'favicon' => Setting::get('favicon_path') ?: Setting::getValue('favicon'),
            'brand_color' => Setting::getValue('brand_color', Branding::DEFAULT_THEME_COLOR),
            'contact_phone' => Setting::getValue('contact_phone', '+1 555 123 4567'),
            'contact_email' => Setting::getValue('contact_email', 'guestservices@example.com'),
            'default_intro' => Setting::getValue('default_intro', 'Your arrival details and local guide are ready when you are.'),
            'gps_verify_message' => Setting::getValue('gps_verify_message', "It's Go Time!"),
            'lock_message' => Setting::getValue('lock_message', "If you'd like quicker access to the unit, you can download the August Home app."),
            'background_check_step_name' => Setting::getValue('background_check_step_name', 'Background Check'),
            'background_check_step_instructions' => Setting::getValue('background_check_step_instructions', 'Please be on the lookout for an email from Airbnb so that you can submit the required hold for incidentals. This hold is refunded after checkout.'),
            'airbnb_payment_instructions' => Setting::getValue('airbnb_payment_instructions', "We'll send you a payment request through [[platform]]. Once it's completed, we'll confirm and send your check-in details."),
            'default_deposit_cap_dollars' => Setting::getValue('default_deposit_cap_cents', 0) / 100,
            'processing_fee_percent' => Setting::getValue('processing_fee_percent', 0),
            'auto_save_enabled' => Setting::get('auto_save_enabled', true),
            'auto_save_delay' => Setting::get('auto_save_delay', 400),
            'notify_session_started' => Setting::get('notify_session_started', true),
            'notify_session_completed' => Setting::get('notify_session_completed', true),
            'notify_assignments' => Setting::get('notify_assignments', true),
            'notify_cleaning_started_global' => Setting::get('notify_cleaning_started_global', true),
            'notify_cleaning_finished_global' => Setting::get('notify_cleaning_finished_global', true),
            'notify_photo_started_global' => Setting::get('notify_photo_started_global', true),
            'notify_task_notes_global' => Setting::get('notify_task_notes_global', true),
            'mandatory_instruction_viewing' => Setting::get('mandatory_instruction_viewing', false),
            'global_required_instruction_views' => Setting::get('global_required_instruction_views', 3),
            'report_header_color' => Setting::get('report_header_color', '#842eb8'),
            'report_status_color' => Setting::get('report_status_color', '#0e7a4b'),
            'report_checklist_color' => Setting::get('report_checklist_color', '#3b82f6'),
            'report_issues_color' => Setting::get('report_issues_color', '#ef4444'),
            'report_photos_color' => Setting::get('report_photos_color', '#0e8a97'),
            'report_time_color' => Setting::get('report_time_color', '#7c3aed'),
            'report_supplies_color' => Setting::get('report_supplies_color', '#ec4899'),
            'report_audit_color' => Setting::get('report_audit_color', '#64748b'),
            'report_button_primary_color' => Setting::get('report_button_primary_color', '#842eb8'),
            'report_button_secondary_color' => Setting::get('report_button_secondary_color', '#ffffff'),
            'site_copyright' => Setting::getValue('site_copyright', '© Dreamzone Media LLC d/b/a Guest Hub'),
            'legal_effective_date' => Setting::getValue('legal_effective_date', date('F j, Y')),
            'terms_page_title' => Setting::getValue('terms_page_title', 'Terms of Service'),
            'privacy_page_title' => Setting::getValue('privacy_page_title', 'Privacy Policy'),
            'rental_contract_page_title' => Setting::getValue('rental_contract_page_title', 'Rental Contract'),
            'terms_url' => Setting::getValue('terms_url', '/terms'),
            'privacy_url' => Setting::getValue('privacy_url', '/privacy-policy'),
            'legal_terms_content' => Setting::getValue('legal_terms_content', ''),
            'legal_privacy_content' => Setting::getValue('legal_privacy_content', ''),
            'legal_rental_contract_content' => Setting::getValue('legal_rental_contract_content', ''),
            'legal_sms_consent_content' => Setting::getValue('legal_sms_consent_content', ''),
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
