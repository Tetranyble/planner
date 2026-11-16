<?php

namespace App\Exceptions\Kyc;

use Illuminate\Http\JsonResponse;
use RuntimeException;

final class OnboardingStepConflict extends RuntimeException
{
    public function __construct(
        public readonly string $expectedStep,
        public readonly ?string $currentStep,
    ) {
        parent::__construct(sprintf(
            'Onboarding step %s is not currently available; current step is %s.',
            $expectedStep,
            $currentStep ?? 'NONE',
        ));
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'ONBOARDING_STEP_CONFLICT',
            'expectedStep' => $this->expectedStep,
            'currentStep' => $this->currentStep,
        ], 409);
    }
}
