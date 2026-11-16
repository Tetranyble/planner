<?php

namespace Tests\Unit\Content;

use App\Services\Ai\GenerateBrandCopy;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AiDraftGatewayTest extends TestCase
{
    public function test_generation_requires_explicit_configuration(): void
    {
        config()->set('services.planner_ai.key', null);
        Http::fake();
        $this->expectException(ValidationException::class);
        app(GenerateBrandCopy::class)->generate(['topic' => 'Test']);
    }

    public function test_output_is_hashed_and_provider_storage_disabled(): void
    {
        config()->set('services.planner_ai.key', 'unit-test-token');
        config()->set('services.planner_ai.model', 'unit-test-model');
        Http::fake(['api.openai.com/*' => Http::response(['output' => [[
            'content' => [['type' => 'output_text', 'text' => 'Draft for editorial review.']],
        ]]], 200)]);
        $result = app(GenerateBrandCopy::class)->generate(['brand' => 'Fixture brand', 'topic' => 'Launch']);
        $this->assertSame('Draft for editorial review.', $result['text']);
        $this->assertSame(hash('sha256', $result['text']), $result['result_hash']);
        Http::assertSent(fn ($request) => $request['store'] === false && $request['model'] === 'unit-test-model');
    }
}
