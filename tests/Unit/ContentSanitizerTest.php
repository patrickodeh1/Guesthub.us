<?php

namespace Tests\Unit;

use App\Services\Channels\ContentSanitizer;
use PHPUnit\Framework\TestCase;

class ContentSanitizerTest extends TestCase
{
    public function test_strips_links_emails_and_phones_and_reports_what_was_removed(): void
    {
        [$clean, $removed] = ContentSanitizer::forOta("Book direct at https://guesthub.us/stay/loft or www.example.com\nEmail me@host.com or call +1 (305) 555-0142 now.");

        $this->assertStringNotContainsString('http', $clean);
        $this->assertStringNotContainsString('@', $clean);
        $this->assertStringNotContainsString('555', $clean);
        $this->assertEqualsCanonicalizing(['link', 'email', 'phone'], $removed);
    }

    public function test_leaves_normal_text_numbers_and_years_alone(): void
    {
        [$clean, $removed] = ContentSanitizer::forOta('Sleeps 4 in 2 bedrooms. Renovated in 2024. Check-in 3:00 PM.');

        $this->assertSame('Sleeps 4 in 2 bedrooms. Renovated in 2024. Check-in 3:00 PM.', $clean);
        $this->assertSame([], $removed);
    }

    public function test_null_input_is_safe(): void
    {
        $this->assertSame(['', []], ContentSanitizer::forOta(null));
    }
}
