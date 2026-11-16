<?php

namespace Tests\Unit\Domain\Privacy;

use App\Domain\Privacy\MetaReviewDisposition;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class MetaReviewDispositionTest extends TestCase
{
    private function clear(): array
    {
        return [
            'disposition' => 'CLEARED',
            'explanation' => 'Deletion evidence checked across provider-derived data stores and exports.',
            'evidence_reference' => 'case/PRIV-2026-0001',
        ];
    }

    public function test_cleared_requires_a_real_evidence_reference(): void
    {
        $this->expectException(ValidationException::class);
        MetaReviewDisposition::validate([...$this->clear(), 'evidence_reference' => '']);
    }

    public function test_cleared_cannot_include_a_retention_basis(): void
    {
        $this->expectException(ValidationException::class);
        MetaReviewDisposition::validate([...$this->clear(), 'legal_basis' => 'LEGAL_OBLIGATION']);
    }

    public function test_retention_requires_legal_basis_and_future_review(): void
    {
        $this->expectException(ValidationException::class);
        MetaReviewDisposition::validate([...$this->clear(), 'disposition' => 'RETAINED_LEGAL']);
    }

    public function test_retention_validation_preserves_evidence_and_policy(): void
    {
        $result = MetaReviewDisposition::validate([
            ...$this->clear(), 'disposition' => 'RETAINED_LEGAL',
            'legal_basis' => 'LEGAL_CLAIMS', 'review_again_at' => now()->addYear()->format('Y-m-d'),
        ]);
        $this->assertSame('RETAINED_LEGAL', $result['disposition']);
        $this->assertSame('LEGAL_CLAIMS', $result['legalBasis']);
    }
}
