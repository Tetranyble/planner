<?php

namespace Tests\Unit\Content;

use App\Domain\Content\PlatformVariantRules;
use PHPUnit\Framework\TestCase;

final class PlatformVariantRulesTest extends TestCase
{
    public function test_all_six_platforms_are_recognized_and_never_claim_dispatch_permission(): void
    {
        foreach (PlatformVariantRules::PROVIDERS as $provider) {
            $check = PlatformVariantRules::inspect($provider, 'Review this draft.');
            self::assertTrue($check['editorially_valid'], $provider);
            self::assertFalse($check['remote_publishable']);
            self::assertSame([], $check['issues']);
        }
    }

    public function test_invalid_captions_fail_closed(): void
    {
        self::assertContains('UNKNOWN_PLATFORM', PlatformVariantRules::inspect('unknown', 'hello')['issues']);
        self::assertContains('EMPTY_CAPTION', PlatformVariantRules::inspect('x', '  ')['issues']);
        self::assertContains('EDITORIAL_CAPTION_LIMIT', PlatformVariantRules::inspect('x', str_repeat('x', 281))['issues']);
        self::assertContains('INVALID_CONTROL_CHARACTER', PlatformVariantRules::inspect('facebook', "test\0value")['issues']);
    }
}
