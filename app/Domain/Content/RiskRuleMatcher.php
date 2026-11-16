<?php

namespace App\Domain\Content;

/** NULL is unknown: match both Yes and No conditions until an editor declares it. */
final class RiskRuleMatcher
{
    public static function matches(?bool $ruleAi, ?bool $ruleSensitive, ?bool $versionAi, ?bool $versionSensitive): bool
    {
        return ($ruleAi === null || $versionAi === null || $ruleAi === $versionAi)
            && ($ruleSensitive === null || $versionSensitive === null || $ruleSensitive === $versionSensitive);
    }
}
