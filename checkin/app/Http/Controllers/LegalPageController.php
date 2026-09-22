<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\View\View;

class LegalPageController extends Controller
{
    public function terms()
    {
        return $this->renderPage(
            Setting::getValue('terms_page_title', 'Terms of Service'),
            Setting::getValue('legal_terms_content', '<p>Terms content has not been configured yet.</p>'),
            'terms'
        );
    }

    public function privacyPolicy()
    {
        return $this->renderPage(
            Setting::getValue('privacy_page_title', 'Privacy Policy'),
            Setting::getValue('legal_privacy_content', '<p>Privacy policy content has not been configured yet.</p>'),
            'privacy-policy'
        );
    }

    public function rentalContract()
    {
        return $this->renderPage(
            Setting::getValue('rental_contract_page_title', 'Rental Contract'),
            Setting::getValue('legal_rental_contract_content', '<p>Rental contract content has not been configured yet.</p>'),
            'rental-contract'
        );
    }

    protected function renderPage(string $title, string $content, string $pageType): View
    {
        return view('public.legal-page', [
            'title' => $title,
            'content' => $content,
            'pageType' => $pageType,
            'effectiveDate' => Setting::getValue('legal_effective_date', date('F j, Y')),
            'siteCopyright' => Setting::getValue('site_copyright', '© Dreamzone Media LLC d/b/a Guest Hub'),
            'siteLogo' => Setting::getValue('site_logo'),
        ]);
    }
}
