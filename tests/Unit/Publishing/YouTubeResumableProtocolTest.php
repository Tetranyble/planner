<?php

namespace Tests\Unit\Publishing;

use App\Domain\Publishing\YouTube\ResumableUploadProtocol as Protocol;
use PHPUnit\Framework\TestCase;

final class YouTubeResumableProtocolTest extends TestCase
{
    public function test_server_308_range_determines_the_next_chunk_offset(): void
    {
        $result = Protocol::acknowledge(308, 'bytes=0-1048575', null, 2000000);
        self::assertSame(['state' => 'OFFSET_CONFIRMED', 'offset' => 1048576], $result);
        self::assertSame('bytes 1048576-1999999/2000000', Protocol::chunk(1048576, 2000000)['headers']['Content-Range']);
        self::assertSame('NEEDS_PROBE', Protocol::acknowledge(503, null, null, 2000000)['state']);
        self::assertSame('UNCERTAIN', Protocol::acknowledge(308, 'bytes=2-10', null, 2000000)['state']);
    }

    public function test_session_url_is_pinned_to_google_exact_upload_origin(): void
    {
        $good = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet%2Cstatus&upload_id=abcdefgh123';
        Protocol::assertSessionUrl($good);
        $this->expectException(\DomainException::class);
        Protocol::assertSessionUrl(str_replace('www.googleapis.com', 'www.googleapis.com.evil.example', $good));
    }

    public function test_independent_verification_requires_exact_channel_and_processing_success(): void
    {
        $body = ['items' => [['id' => 'video123456', 'snippet' => ['channelId' => 'UCtest1234567890123456'],
            'status' => ['uploadStatus' => 'processed'], 'processingDetails' => ['processingStatus' => 'succeeded']]]];
        self::assertSame('VERIFIED', Protocol::verifiedVideo('video123456', 'UCtest1234567890123456', $body)['state']);
        self::assertSame('UNCERTAIN', Protocol::verifiedVideo('video123456', 'wrongchannel', $body)['state']);
        $body['items'][0]['processingDetails']['processingStatus'] = 'processing';
        self::assertSame('PROCESSING', Protocol::verifiedVideo('video123456','UCtest1234567890123456',$body)['state']);
    }
}
