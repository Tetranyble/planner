<?php

namespace Tests\Unit\Content;

use App\Domain\Content\ApprovalPayloadSnapshot;
use PHPUnit\Framework\TestCase;

final class ApprovalPayloadSnapshotTest extends TestCase
{
    public function test_approval_snapshot_hash_is_repeatable_and_policy_target_media_changes_invalidate_it(): void
    {
        $snapshot = ['schema_version' => 1, 'version_uuid' => 'v1', 'content_hash' => str_repeat('a', 64),
            'target_payload_hash' => str_repeat('b', 64), 'media' => [['sha256' => str_repeat('c', 64), 'sort_order' => 0]], 'approval_policy_version' => 3];
        $hash = ApprovalPayloadSnapshot::hash($snapshot);
        $this->assertSame($hash, ApprovalPayloadSnapshot::hash($snapshot));
        $changed = $snapshot;
        $changed['approval_policy_version'] = 4;
        $this->assertNotSame($hash, ApprovalPayloadSnapshot::hash($changed));
        $changed = $snapshot;
        $changed['media'][0]['sha256'] = str_repeat('d', 64);
        $this->assertNotSame($hash, ApprovalPayloadSnapshot::hash($changed));
        $changed = $snapshot;
        $changed['target_payload_hash'] = str_repeat('e', 64);
        $this->assertNotSame($hash, ApprovalPayloadSnapshot::hash($changed));
    }
}
