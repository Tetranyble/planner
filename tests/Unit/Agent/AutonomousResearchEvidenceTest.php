<?php

namespace Tests\Unit\Agent;

use App\Domain\Agent\AutonomousResearchEvidence;
use PHPUnit\Framework\TestCase;

final class AutonomousResearchEvidenceTest extends TestCase
{
    private function response(string $url = 'https://example.org/study'): array
    {
        return ['output' => [
            ['type' => 'web_search_call', 'status' => 'completed', 'action' => ['type' => 'search']],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'A cited finding.',
                'annotations' => [['type' => 'url_citation', 'url' => $url, 'title' => 'Published study']]]]],
        ]];
    }

    public function test_only_completed_cited_web_research_is_accepted(): void
    {
        $e = AutonomousResearchEvidence::fromResponse($this->response());
        $this->assertCount(1, $e['sources']);
        $this->assertFalse($e['sources_verified']);
        $this->assertSame('A cited finding.', $e['findings']);
        $this->assertSame(64, strlen($e['research_hash']));
    }

    public function test_missing_web_call_or_untrusted_url_is_rejected(): void
    {
        $bad = $this->response('http://localhost/private');
        $this->expectException(\UnexpectedValueException::class);
        AutonomousResearchEvidence::fromResponse($bad);
    }

    public function test_annotations_without_completed_search_are_rejected(): void
    {
        $bad = $this->response();
        $bad['output'][0]['status'] = 'failed';
        $this->expectException(\UnexpectedValueException::class);
        AutonomousResearchEvidence::fromResponse($bad);
    }
}
