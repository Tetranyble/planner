<?php

namespace Tests\Feature\Agents;

use App\Services\Agents\SearchWithAttributedSources;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PlannerHostedResearchGatewayTest extends TestCase
{
    public function test_cited_response_is_normalized_without_persisting_raw_provider_payload(): void
    {
        config()->set('services.planner_ai.key', 'synthetic-test-only');
        config()->set('services.planner_ai.research_model', 'gpt-6-astra');
        Http::fake(['api.openai.com/v1/responses' => Http::response(['output' => [
            ['type' => 'web_search_call', 'status' => 'completed', 'action' => ['type' => 'search']],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Editorial finding with citations.',
                'annotations' => [['type' => 'url_citation', 'url' => 'https://example.org/study', 'title' => 'Study']]]]],
        ], 'usage' => ['input_tokens' => 100, 'output_tokens' => 50]], 200)]);
        $result = app(SearchWithAttributedSources::class)->research('A real research topic', 'Investigate a real audience');
        $this->assertSame('Editorial finding with citations.', $result['findings']);
        $this->assertCount(1, $result['sources']);
        $this->assertFalse($result['sources_verified']);
        $this->assertArrayNotHasKey('output', $result);
        Http::assertSent(fn ($req) => $req->url() === 'https://api.openai.com/v1/responses'
         && $req['tools'][0]['type'] === 'web_search' && $req['store'] === false);
    }

    public function test_uncited_generated_answer_does_not_count_as_researched(): void
    {
        config()->set('services.planner_ai.key', 'synthetic-test-only');
        Http::fake(['api.openai.com/v1/responses' => Http::response(['output' => [
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Invented answer', 'annotations' => []]]],
        ]], 200)]);
        $this->expectException(\UnexpectedValueException::class);
        app(SearchWithAttributedSources::class)->research('Topic with citations', 'Find verifiable documentation');
    }
}
