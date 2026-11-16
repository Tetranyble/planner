<?php

namespace Tests\Unit\Content;

use App\Models\ContentVersion;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class ContentVersionImmutableTest extends TestCase
{
    public function test_event_dispatcher_cannot_be_used_to_bypass_append_only_updates(): void
    {
        Event::fake();
        $version = new ContentVersion;
        $version->exists = true;
        $version->setRawAttributes(['id' => 1, 'ai_generated' => false], true);
        $version->ai_generated = true;
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Content history must be append-only.');
        $version->save();
    }
}
