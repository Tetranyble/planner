<?php

namespace Tests\Unit\Publishing;

use App\Domain\Publishing\Media\MediaStageTransition;
use PHPUnit\Framework\TestCase;

final class MediaStageTransitionTest extends TestCase
{
    public function test_instagram_container_and_status_do_not_imply_publication(): void
    {
        $created = MediaStageTransition::next('instagram', 'CREATE_CONTAINER',
            ['state' => 'CONTAINER_CREATED', 'provider_receipt' => '123456'], 0);
        $this->assertSame('NEXT_STAGE', $created['state']);
        $this->assertSame('CHECK_CONTAINER_STATUS', $created['operation']);
        $published = MediaStageTransition::next('instagram', 'PUBLISH_CONTAINER',
            ['state' => 'PUBLISHED', 'provider_receipt' => '987654'], 0);
        $this->assertSame('AWAITING_VERIFICATION', $published['state']);
    }

    public function test_read_only_provider_polling_is_bounded(): void
    {
        $this->assertSame('WAITING_PROVIDER', MediaStageTransition::next('tiktok', 'FETCH_PUBLISH_STATUS',
            ['state' => 'PROCESSING'], 11)['state']);
        $this->assertSame('MANUAL_REQUIRED', MediaStageTransition::next('tiktok', 'FETCH_PUBLISH_STATUS',
            ['state' => 'PROCESSING'], 12)['state']);
    }

    public function test_ambiguous_responses_cannot_trigger_retries(): void
    {
        $out = MediaStageTransition::next('instagram', 'CREATE_CONTAINER',
            ['state' => 'UNCERTAIN', 'code' => 'REMOTE_RESPONSE_UNKNOWN'], 0);
        $this->assertSame('UNCERTAIN', $out['state']);
        $this->assertNotSame('NEXT_STAGE',$out['state']);
    }
}
