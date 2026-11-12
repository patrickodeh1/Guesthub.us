<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Setting;

/**
 * The long-term lease contract. Each property has its own editable lease
 * template (properties.lease_agreement). Until the host saves one, it is
 * seeded from the property's rental agreement terms, then the global
 * contract. When an offer is prepared the template is copied onto the offer,
 * where the host redrafts it before signing.
 */
class LeaseAgreementService
{
    /** Placeholders available in a lease contract (shown as chips on the property page). */
    public function placeholders(): array
    {
        return [
            'applicant_name', 'host_name', 'property_name', 'property_address',
            'start_date', 'end_date', 'nights', 'monthly_rent', 'deposit', 'total', 'initial_due',
            'payment_schedule', 'website_name',
            'host_signed_name', 'host_signed_at', 'tenant_signed_name', 'tenant_signed_at', 'tenant_signed_ip',
            'agreement_version',
        ];
    }

    public function defaultTemplate(?string $terms = null): string
    {
        $terms = filled($terms) ? $terms : '<p>Enter the lease terms for this property here.</p>';

        $html = <<<'HTML'
<h2>Lease details</h2>
<table class="meta">
<tr><td class="k">Tenant</td><td>[[applicant_name]]</td></tr>
<tr><td class="k">Host</td><td>[[host_name]]</td></tr>
<tr><td class="k">Property</td><td>[[property_name]] - [[property_address]]</td></tr>
<tr><td class="k">Lease term</td><td>[[start_date]] to [[end_date]] ([[nights]] nights)</td></tr>
<tr><td class="k">Monthly rent</td><td>[[monthly_rent]]</td></tr>
<tr><td class="k">Security deposit</td><td>[[deposit]]</td></tr>
<tr><td class="k">Total over the term</td><td>[[total]]</td></tr>
<tr><td class="k">Due at signing</td><td>[[initial_due]]</td></tr>
</table>
<h2>Payment schedule</h2>
[[payment_schedule]]
<h2>Terms and conditions</h2>
@@TERMS@@
<h2>Signatures</h2>
<table class="meta">
<tr><td class="k">Host</td><td>[[host_signed_name]] - [[host_signed_at]]</td></tr>
<tr><td class="k">Tenant</td><td>[[tenant_signed_name]] - [[tenant_signed_at]]</td></tr>
<tr><td class="k">Tenant IP address</td><td>[[tenant_signed_ip]]</td></tr>
<tr><td class="k">Agreement version</td><td>[[agreement_version]]</td></tr>
</table>
HTML;

        return str_replace('@@TERMS@@', $terms, $html);
    }

    /** Pull just the terms out of a rental agreement, so booking-only fields do not leak into a lease. */
    public function termsFrom(string $rental): string
    {
        if (preg_match('/<h2>\s*Terms and conditions\s*<\/h2>(.*?)(<h2>|\z)/is', $rental, $m) && filled(trim($m[1]))) {
            return trim($m[1]);
        }

        return $rental;
    }

    /** What a property's lease contract looks like before the host has saved their own. */
    public function seedFor(?Property $property): string
    {
        $rental = '';
        if ($property && filled($property->rental_agreement)) {
            $rental = (string) $property->rental_agreement;
        } else {
            foreach (['legal_rental_contract_content', 'rental_contract_content', 'legal_rental_agreement_content'] as $key) {
                $value = (string) Setting::getValue($key, '');
                if (filled($value)) {
                    $rental = $value;
                    break;
                }
            }
        }

        return $this->defaultTemplate(filled($rental) ? $this->termsFrom($rental) : null);
    }

    /** The template a new offer starts from: the property's own, else the seed. */
    public function templateFor(?Property $property): string
    {
        if ($property && filled($property->lease_agreement)) {
            return (string) $property->lease_agreement;
        }

        return $this->seedFor($property);
    }
}
