<?php

namespace Tests\Feature;

use App\Services\IdDocumentExtractor;
use App\Services\LeaseApplicationIdScanService;
use Tests\TestCase;

class LeaseApplicationIdScanServiceTest extends TestCase
{
    public function test_extracts_only_name_address_and_last_four_without_returning_dob_or_full_id(): void
    {
        $extractor = \Mockery::mock(IdDocumentExtractor::class);
        $extractor->shouldReceive('readRawText')->once()->with('private/id.jpg')->andReturn(
            "Name: Jordan Applicant\nAddress: 12 Ocean Road\nID Number: A123456789\nDOB: 01/02/1990",
        );

        $result = (new LeaseApplicationIdScanService($extractor))->scan('private/id.jpg');

        $this->assertSame([
            'name' => 'Jordan Applicant',
            'address' => '12 Ocean Road',
            'number_last4' => '6789',
        ], $result);
    }

    public function test_unreadable_scan_returns_empty_fields_for_retry_and_manual_review(): void
    {
        $extractor = \Mockery::mock(IdDocumentExtractor::class);
        $extractor->shouldReceive('readRawText')->once()->with('private/id.jpg')->andReturn(null);

        $result = (new LeaseApplicationIdScanService($extractor))->scan('private/id.jpg');

        $this->assertSame(['name' => null, 'address' => null, 'number_last4' => null], $result);
    }
}
