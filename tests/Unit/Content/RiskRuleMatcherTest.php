<?php

namespace Tests\Unit\Content;

use App\Domain\Content\RiskRuleMatcher;
use PHPUnit\Framework\TestCase;

final class RiskRuleMatcherTest extends TestCase
{
    public function test_unknown_never_excludes_a_conditional_rule(): void
    {
        foreach ([true, false, null] as $expected) {
            $this->assertTrue(RiskRuleMatcher::matches($expected, $expected, null, null));
        }
    }

    public function test_known_declared_values_exclude_only_contradictory_rules(): void
    {
        $this->assertFalse(RiskRuleMatcher::matches(true, null, false, false));
        $this->assertFalse(RiskRuleMatcher::matches(null, false, true, true));
        $this->assertTrue(RiskRuleMatcher::matches(true, false, true, false));
        $this->assertTrue(RiskRuleMatcher::matches(null, null, false, true));
    }
}
