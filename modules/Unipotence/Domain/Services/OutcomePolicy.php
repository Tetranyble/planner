<?php

namespace Modules\Unipotence\Domain\Services;

use Modules\Unipotence\Domain\Data\UnipotenceOutcome;
use Modules\Unipotence\Domain\Enums\OutcomeDisposition;

final class OutcomePolicy
{
    public function decide(UnipotenceOutcome $outcome): OutcomeDisposition
    {
        if (! $outcome->replayable || $outcome->status === 408 || $outcome->status >= 500) {
            return OutcomeDisposition::Indeterminate;
        }

        if (in_array($outcome->status, [422, 425, 429], true)) {
            return OutcomeDisposition::Release;
        }

        return OutcomeDisposition::Complete;
    }
}
