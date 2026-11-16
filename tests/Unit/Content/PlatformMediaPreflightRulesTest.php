<?php

namespace Tests\Unit\Content;

use App\Domain\Content\PlatformMediaPreflightRules as R;
use PHPUnit\Framework\TestCase;

final class PlatformMediaPreflightRulesTest extends TestCase
{
    public function test_media_required_for_image_video_first_networks(): void
    {
        foreach (['instagram', 'tiktok', 'youtube'] as $provider) {
            self::assertContains('MEDIA_REQUIRED', R::inspect($provider, [])['issues']);
        }
        self::assertTrue(R::inspect('facebook', [])['editorially_valid']);
    }

    public function test_image_mime_and_dimensions_are_checked_without_using_storage(): void
    {
        $result = R::inspect('instagram', [[
            'mime_type' => 'image/webp', 'byte_size' => 1000, 'width' => 1024, 'height' => 1024,
        ]]);
        self::assertContains('INSTAGRAM_IMAGE_JPEG_REQUIRED', $result['issues']);
        self::assertFalse($result['remote_publishable']);
    }

    public function test_youtube_requires_video_and_duration(): void
    {
        $result = R::inspect('youtube', [[
            'mime_type' => 'video/mp4', 'byte_size' => 1000000, 'duration_ms' => 0,
        ]]);
        self::assertContains('VIDEO_DURATION_MISSING', $result['issues']);
    }
}
