<?php

namespace Tests\Unit\Publishing;

use App\Domain\Publishing\Media\MediaStepRequest;
use PHPUnit\Framework\TestCase;

final class MediaStepEgressPolicyTest extends TestCase
{
    public function test_only_pinned_provider_hosts_and_routes_are_accepted(): void
    {
        $ig = new MediaStepRequest('instagram', 'CREATE_CONTAINER', 'POST',
            'https://graph.facebook.com/v27.0/178123456789/media');
        $this->assertSame('graph.facebook.com', $ig->safeMetadata()['host']);
        $tiktok = new MediaStepRequest('tiktok', 'INITIALIZE_VIDEO', 'POST',
            'https://open.tiktokapis.com/v2/post/publish/video/init/');
        $this->assertSame('open.tiktokapis.com', $tiktok->safeMetadata()['host']);
        $youtube = new MediaStepRequest('youtube', 'INITIALIZE_UPLOAD_SESSION', 'POST',
            'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet%2Cstatus');
        $this->assertSame('www.googleapis.com', $youtube->safeMetadata()['host']);
    }

    public function test_untrusted_redirect_or_query_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        new MediaStepRequest('instagram', 'CREATE_CONTAINER', 'POST',
            'https://graph.facebook.com/v27.0/178123456789/media?access_token=secret');
    }
}
