<?php

namespace Tests\Unit\Ai;

use App\Domain\Ai\ProviderTokenEvidence;
use PHPUnit\Framework\TestCase;

final class ProviderTokenEvidenceTest extends TestCase
{
    public function test_valid_usage_is_observed_without_converting_unknown_to_zero(): void
    {
        self::assertSame(['input_tokens' => 125, 'output_tokens' => 50],
            ProviderTokenEvidence::fromResponse(['usage' => ['input_tokens' => 125, 'output_tokens' => 50]]));
        self::assertSame(['input_tokens' => null, 'output_tokens' => null],
            ProviderTokenEvidence::fromResponse(['output' => []]));
        self::assertSame(['input_tokens' => null, 'output_tokens' => null],
            ProviderTokenEvidence::fromResponse(['usage' => ['input_tokens' => '123', 'output_tokens' => -1]]));
        self::assertSame(['input_tokens' => 0, 'output_tokens' => 0],
            ProviderTokenEvidence::fromResponse(['usage' => ['input_tokens' => 0, 'output_tokens' => 0]]));
    }
}
