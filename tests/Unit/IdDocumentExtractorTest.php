<?php

namespace Tests\Unit;

use App\Services\IdDocumentExtractor;
use App\Services\IdExtractionResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Parsing of text as returned by Google Vision DOCUMENT_TEXT_DETECTION.
 * No network and no database: the parsing methods are exercised directly.
 */
class IdDocumentExtractorTest extends TestCase
{
    private function parser(): object
    {
        return new class('test-key') extends IdDocumentExtractor
        {
            public function parse(string $raw): array
            {
                $lines = $this->normalizeLines($raw);

                return [
                    'name' => $this->findName($lines),
                    'dob' => $this->findLabeledDate($lines, [
                        'date of birth', 'dob', 'birth', 'naiss', 'nacimiento',
                    ], true)?->format('Y-m-d'),
                    'exp' => $this->findLabeledDate($lines, [
                        'expiry', 'expiration', 'exp date', 'exp.', 'valid until', 'valid thru', 'date of expiry',
                    ], false)?->format('Y-m-d'),
                ];
            }

            public function date(string $text): ?string
            {
                return $this->parseDate($text)?->format('Y-m-d');
            }

            public function redactPublic(string $value): string
            {
                return $this->redact($value);
            }
        };
    }

    public function test_us_month_first_dates_are_read_correctly(): void
    {
        $p = $this->parser();

        // Day over 12: unambiguous. These used to roll over into the wrong year.
        $this->assertSame('1990-03-15', $p->date('DOB 03/15/1990'));
        $this->assertSame('1985-12-31', $p->date('12/31/1985'));
        $this->assertSame('1988-07-22', $p->date('07/22/1988'));

        // Both numbers 12 or less: US month-first is the default.
        $this->assertSame('1990-04-05', $p->date('DOB 04/05/1990'));

        // Day-first still works when the first number can only be a day.
        $this->assertSame('1990-03-15', $p->date('15/03/1990'));
        $this->assertSame('1990-03-15', $p->date('15-03-1990'));
        $this->assertSame('1990-03-15', $p->date('15.03.1990'));
    }

    public function test_other_date_formats(): void
    {
        $p = $this->parser();

        $this->assertSame('1990-03-15', $p->date('15 MAR 1990'));
        $this->assertSame('1990-03-15', $p->date('15 Mar. 1990'));
        $this->assertSame('1990-09-05', $p->date('5 SEPT 1990'));
        $this->assertSame('1990-03-15', $p->date('MAR 15, 1990'));
        $this->assertSame('1990-03-15', $p->date('1990-03-15'));
    }

    public function test_impossible_dates_are_rejected_not_rolled_over(): void
    {
        $p = $this->parser();

        $this->assertNull($p->date('02/30/1990'));
        $this->assertNull($p->date('13/13/1990'));
        $this->assertNull($p->date('31/04/1990'));
        $this->assertNull($p->date('00/10/1990'));
        $this->assertNull($p->date('no date here'));
        $this->assertNull($p->date('15 XYZ 1990'));
    }

    public function test_leap_day(): void
    {
        $p = $this->parser();

        $this->assertSame('1992-02-29', $p->date('02/29/1992'));
        $this->assertNull($p->date('02/29/1991'));
    }

    public function test_labeled_dob_and_expiry_end_to_end(): void
    {
        $p = $this->parser();

        $r = $p->parse("DRIVER LICENSE\nSurname DOE\nGiven Names JOHN\nDOB 03/15/1990\nExpiration 03/15/2030");
        $this->assertSame('JOHN DOE', $r['name']);
        $this->assertSame('1990-03-15', $r['dob']);
        $this->assertSame('2030-03-15', $r['exp']);

        // Value on the line after the label.
        $r = $p->parse("Date of Birth\n07/22/1988");
        $this->assertSame('1988-07-22', $r['dob']);

        // Passport style.
        $r = $p->parse("PASSPORT\nSurname\nDOE\nGiven names\nJOHN MICHAEL\nDate of birth\n15 MAR 1990\nDate of expiry\n14 MAR 2030");
        $this->assertSame('JOHN MICHAEL DOE', $r['name']);
        $this->assertSame('1990-03-15', $r['dob']);
        $this->assertSame('2030-03-14', $r['exp']);
    }

    public function test_dob_in_the_future_or_far_past_is_ignored(): void
    {
        $p = $this->parser();

        $this->assertNull($p->parse('DOB 01/01/2999')['dob']);
        $this->assertNull($p->parse('DOB 01/01/1800')['dob']);
    }

    public function test_name_layouts(): void
    {
        $p = $this->parser();

        // LN / FN labels, value on the same line.
        $this->assertSame('JOHN MICHAEL DOE', $p->parse("LN DOE\nFN JOHN MICHAEL\nDOB 03/15/1990")['name']);

        // LN / FN labels, value on the following line.
        $this->assertSame('JOHN DOE', $p->parse("LN\nDOE\nFN\nJOHN")['name']);

        // AAMVA numbered fields.
        $this->assertSame('JOHN MICHAEL DOE', $p->parse("1 DOE\n2 JOHN MICHAEL\n3 DOB 03/15/1990")['name']);

        // "LAST, FIRST".
        $this->assertSame('JOHN DOE', $p->parse("Name: DOE, JOHN\nDOB 03/15/1990")['name']);
        $this->assertSame('JOHN MICHAEL DOE', $p->parse("Name\nDOE, JOHN MICHAEL")['name']);

        // Plain label.
        $this->assertSame('JOHN DOE', $p->parse('Name: JOHN DOE')['name']);
    }

    public function test_name_is_left_null_rather_than_guessed(): void
    {
        $p = $this->parser();

        // Unlabeled lines: could be anything, so no guess.
        $this->assertNull($p->parse("DRIVER LICENSE\nDOE\nJOHN MICHAEL\n123 MAIN ST\nDOB 03/15/1990")['name']);

        // Only one of the two numbered fields is present.
        $this->assertNull($p->parse("1 DOE\nDOB 03/15/1990")['name']);

        // An address must not be mistaken for a number-1 name field.
        $this->assertNull($p->parse("1 MAIN ST\n123 OAK AVE\nDOB 03/15/1990")['name']);
    }

    public function test_expired_on_the_day_it_expires_is_not_yet_expired(): void
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        $this->assertFalse((new IdExtractionResult(expiryDate: $today))->isExpired());
        $this->assertTrue((new IdExtractionResult(expiryDate: $yesterday))->isExpired());
        $this->assertFalse((new IdExtractionResult(expiryDate: null))->isExpired());
    }

    public function test_api_key_is_redacted_from_log_text(): void
    {
        $p = $this->parser();

        $message = 'cURL error 28: Operation timed out after 20001 milliseconds for '
            .'https://vision.googleapis.com/v1/images:annotate?key=test-key';
        $this->assertStringNotContainsString('test-key', $p->redactPublic($message));

        // Pattern-based redaction works even when the literal key is unknown.
        $other = 'failed for https://vision.googleapis.com/v1/images:annotate?key=AIzaSyABC-def_123&x=1';
        $out = $p->redactPublic($other);
        $this->assertStringNotContainsString('AIzaSyABC', $out);
        $this->assertStringContainsString('x=1', $out);
    }

    public function test_extract_reports_why_it_failed_and_never_logs_the_key(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('photo-ids/x.jpg', 'fake-image-bytes');

        // Happy path.
        Http::fake(['vision.googleapis.com/*' => Http::response(['responses' => [[
            'fullTextAnnotation' => ['text' => "LN DOE\nFN JOHN\nDOB 03/15/1990\nEXP 03/15/2030"],
        ]]], 200)]);
        $r = (new IdDocumentExtractor('secret-key-123'))->extract('photo-ids/x.jpg');
        $this->assertNull($r->failureReason);
        $this->assertSame('JOHN DOE', $r->name);
        $this->assertSame('1990-03-15', $r->dateOfBirth->format('Y-m-d'));

        // 200 but no text found.
        Http::fake(['vision.googleapis.com/*' => Http::response(['responses' => [[]]], 200)]);
        $r = (new IdDocumentExtractor('secret-key-123'))->extract('photo-ids/x.jpg');
        $this->assertSame('empty_text', $r->failureReason);

        // HTTP 403 from Google (API not enabled / billing / restricted key).
        Http::fake(['vision.googleapis.com/*' => Http::response(
            ['error' => ['code' => 403, 'status' => 'PERMISSION_DENIED', 'message' => 'Cloud Vision API has not been used in project 1 before or it is disabled.']],
            403
        )]);
        $r = (new IdDocumentExtractor('secret-key-123'))->extract('photo-ids/x.jpg');
        $this->assertSame('http_403', $r->failureReason);
        $this->assertNull($r->name);

        // Missing key.
        $r = (new IdDocumentExtractor(''))->extract('photo-ids/x.jpg');
        $this->assertSame('no_api_key', $r->failureReason);

        // Keep this case LAST: it puts a strict expectation on the logger.
        // A connection failure whose message contains the key-bearing URL must
        // log exactly one error, and its context must not contain the key.
        Log::shouldReceive('error')->once()->withArgs(function ($message, $context = []) {
            return ! str_contains(json_encode($context), 'secret-key-123')
                && str_contains(json_encode($context), '[redacted]');
        });
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException(
                'cURL error 28: Operation timed out for https://vision.googleapis.com/v1/images:annotate?key=secret-key-123'
            );
        });
        $r = (new IdDocumentExtractor('secret-key-123'))->extract('photo-ids/x.jpg');
        $this->assertSame('exception', $r->failureReason);
    }
}
