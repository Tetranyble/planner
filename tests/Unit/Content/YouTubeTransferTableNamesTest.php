<?php

namespace Tests\Unit\Content;

use App\Models\YouTubeResumableAttempt;
use App\Models\YouTubeResumableEvent;
use App\Models\YouTubeResumableTransfer;
use Tests\TestCase;

final class YouTubeTransferTableNamesTest extends TestCase
{
    public function test_all_three_models_use_the_existing_migration_table_names(): void
    {
        $this->assertSame('youtube_resumable_transfers', (new YouTubeResumableTransfer)->getTable());
        $this->assertSame('youtube_resumable_attempts', (new YouTubeResumableAttempt)->getTable());
        $this->assertSame('youtube_resumable_events', (new YouTubeResumableEvent)->getTable());
    }
}
