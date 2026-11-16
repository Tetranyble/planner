<?php

namespace Tests\Unit\Content;

use App\Domain\Content\ProviderPublishCapabilityMatrix;
use PHPUnit\Framework\TestCase;

final class ProviderPublishCapabilityMatrixTest extends TestCase
{
    public function test_catalogue_covers_six_providers_without_generic_wildcard(): void
    {
        self::assertSame([
            'facebook' => 'post.publish', 'instagram' => 'media.publish', 'linkedin' => 'post.publish',
            'x' => 'post.publish', 'tiktok' => 'video.publish', 'youtube' => 'video.publish',
        ], ProviderPublishCapabilityMatrix::providers());
        self::assertNull(ProviderPublishCapabilityMatrix::capabilityFor('not-a-provider'));
        self::assertSame('media.publish', ProviderPublishCapabilityMatrix::capabilityFor('INSTAGRAM'));
    }
}
