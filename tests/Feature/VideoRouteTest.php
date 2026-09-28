<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoRouteTest extends TestCase
{
    public function test_video_route_handles_byte_ranges()
    {
        Storage::disk('public')->put('videos/test_range.mp4', str_repeat('0', 1024)); // 1KB dummy

        $response = $this->get('/file/videos/test_range.mp4', [
            'Range' => 'bytes=0-100',
        ]);

        $response->assertStatus(206);
        $response->assertHeader('Content-Type', 'video/mp4');
        $response->assertHeader('Accept-Ranges', 'bytes');
        
        // Let's assert we actually got a Content-Range header
        $this->assertTrue($response->headers->has('Content-Range'));
        $this->assertStringContainsString('bytes 0-100/1024', $response->headers->get('Content-Range'));

        Storage::disk('public')->delete('videos/test_range.mp4');
    }
}
