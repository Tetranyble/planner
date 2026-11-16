<?php

namespace Tests\Unit\Publishing;

use App\Domain\Publishing\Media\MediaPublicationProtocols as Protocol;
use App\Domain\Publishing\Media\MediaReleaseRequirements;
use PHPUnit\Framework\TestCase;

final class MediaPublicationProtocolsTest extends TestCase
{
    public function test_all_providers_are_multistep_and_cannot_auto_retry_sent_posts(): void
    {
        foreach (['instagram' => 'image/jpeg', 'tiktok' => 'video/mp4', 'youtube' => 'video/mp4'] as $provider => $mime) {
            $result = Protocol::plan($provider, $mime);
            $this->assertGreaterThanOrEqual(4, count($result['stages']));
            $this->assertFalse($result['automatic_retry_after_send']);
            $this->assertFalse(MediaReleaseRequirements::checklist($provider, true, true, true)['can_publish']);
        }
    }

    public function test_tiktok_requires_creator_consent_and_current_allowed_privacy(): void
    {
        $this->expectException(\DomainException::class);
        Protocol::initRequest('tiktok', 'any-open-id', 'video/mp4', 'Caption',
            'https://media.example.com/video.mp4', ['media.example.com'], [
                'creator_options_checked' => true, 'explicit_consent' => false, 'privacy_level' => 'PUBLIC_TO_EVERYONE',
                'allowed_privacy_levels' => ['PUBLIC_TO_EVERYONE']]);
    }

    public function test_missing_provider_receipt_is_quarantined(): void
    {
        $this->assertSame('UNCERTAIN', Protocol::providerState('instagram', 'PUBLISH_CONTAINER', [])['state']);
        $this->assertSame('UNCERTAIN', Protocol::providerState('tiktok', 'FETCH_PUBLISH_STATUS',
            ['data' => ['status' => 'PUBLISH_COMPLETE']])['state']);
        $this->assertSame('UPLOAD_CONFIRMED', Protocol::providerState('youtube', 'UPLOAD_COMPLETE',
            ['id' => 'abc123xyz'])['state']);
    }
}
