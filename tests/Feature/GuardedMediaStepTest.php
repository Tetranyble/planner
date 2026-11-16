<?php

namespace Tests\Feature;

use App\Domain\Publishing\Media\GuardedMediaStep;
use App\Domain\Publishing\Media\MediaStepAuthorization;
use App\Domain\Publishing\Media\MediaStepRequest;
use App\Domain\Publishing\Media\MediaStepTransportContract;
use Tests\TestCase;

final class GuardedMediaStepTest extends TestCase
{
    public function test_denied_authorization_does_not_call_transport(): void
    {
        $transport = new class implements MediaStepTransportContract
        {
            public bool $called = false;

            public function send(MediaStepRequest $request, string $accessToken): array
            {
                $this->called = true;

                return ['status' => 200, 'body' => ['id' => '178999999999'], 'may_have_sent' => true];
            }
        };
        config()->set('planner.media_dispatch_released', false);
        $request = new MediaStepRequest('instagram', 'CREATE_CONTAINER', 'POST',
            'https://graph.facebook.com/v27.0/123456789/media', ['caption' => 'Test']);
        $proof = new MediaStepAuthorization(true, true, true, true, true, true, true);
        $result = (new GuardedMediaStep($transport))->execute($request, $proof, 'not-a-real-token');
        $this->assertSame('BLOCKED', $result['state']);
        $this->assertFalse($transport->called);
    }

    public function test_youtube_session_does_not_start_without_a_storage_stream_driver(): void
    {
        $transport = new class implements MediaStepTransportContract
        {
            public function send(MediaStepRequest $request, string $accessToken): array
            {
                throw new \LogicException('Media transport must not run');
            }
        };
        $request = new MediaStepRequest('youtube', 'INITIALIZE_UPLOAD_SESSION', 'POST',
            'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet%2Cstatus');
        $proof = new MediaStepAuthorization(true, true, true, true, true, true, true);
        $result = (new GuardedMediaStep($transport))->execute($request, $proof, 'not-a-real-token');
        $this->assertSame('YOUTUBE_RESUMABLE_STREAM_NOT_AVAILABLE',$result['code']);
    }
}
