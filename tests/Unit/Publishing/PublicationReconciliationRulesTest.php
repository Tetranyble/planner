<?php

namespace Tests\Unit\Publishing;

use App\Domain\Publishing\PublicationReconciliationRules as Rules;
use PHPUnit\Framework\TestCase;

final class PublicationReconciliationRulesTest extends TestCase
{
    public function test_independent_reviewer_and_uncertain_state_are_required(): void
    {
        $this->assertTrue(Rules::canReview('UNCERTAIN', 5, 6, false));
        $this->assertFalse(Rules::canReview('UNCERTAIN', 5, 5, false));
        $this->assertFalse(Rules::canReview('PUBLISHED', 5, 6, false));
        $this->assertFalse(Rules::canReview('UNCERTAIN', 5, 6, true));
    }
}
