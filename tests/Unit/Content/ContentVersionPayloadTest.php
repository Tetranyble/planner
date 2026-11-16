<?php

namespace Tests\Unit\Content;

use App\Domain\Content\ContentVersionPayload;
use PHPUnit\Framework\TestCase;

final class ContentVersionPayloadTest extends TestCase
{
    public function test_tri_state_declarations_are_part_of_immutable_hash_without_mutating_legacy_hash(): void
    {
        $legacy = ContentVersionPayload::hash('Example', []);
        $this->assertSame($legacy, ContentVersionPayload::hash('Example', [], null, null));
        $this->assertNotSame($legacy, ContentVersionPayload::hash('Example', [], false, false));
        $this->assertNotSame(ContentVersionPayload::hash('Example', [], true, false), ContentVersionPayload::hash('Example', [], false, false));
        $this->assertNotSame(ContentVersionPayload::hash('Example', [], true, null), ContentVersionPayload::hash('Example', [], true, false));
    }

    public function test_identical_content_has_identical_hash_and_media_order_is_significant(): void
    {
        $first = ContentVersionPayload::hash('Example', ['a', 'b']);
        $this->assertSame($first, ContentVersionPayload::hash('Example', ['a', 'b']));
        $this->assertNotSame($first, ContentVersionPayload::hash('Example', ['b', 'a']));
        $this->assertNotSame($first, ContentVersionPayload::hash('Example revised', ['a', 'b']));
        $this->assertSame(['schema_version' => 1, 'format' => 'plain_text'], ContentVersionPayload::body());
    }
}
