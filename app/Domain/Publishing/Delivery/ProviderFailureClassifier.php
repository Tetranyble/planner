<?php

namespace App\Domain\Publishing\Delivery;

/** Conservative HTTP classification. A transport timeout may mean the provider accepted the post. */
final class ProviderFailureClassifier
{
    public static function classify(?int $status, bool $requestMayHaveBeenSent, ?int $retryAfter = null): DispatchOutcome
    {
        if ($status === null) {
            return new DispatchOutcome('UNCERTAIN', 'TRANSPORT_AMBIGUOUS');
        }
        if ($status === 429) {
            return new DispatchOutcome('RATE_LIMITED', 'RATE_LIMIT', $status, max(1, min(86400, $retryAfter ?? 60)));
        }
        if (in_array($status, [408, 425], true)) {
            return new DispatchOutcome('UNCERTAIN', 'PROVIDER_TIMEOUT', $status);
        }
        if ($status >= 500) {
            return new DispatchOutcome($requestMayHaveBeenSent ? 'UNCERTAIN' : 'RETRYABLE',
                $requestMayHaveBeenSent ? 'SERVER_RESPONSE_AMBIGUOUS' : 'SERVER_UNAVAILABLE', $status);
        }
        if (in_array($status, [401, 403], true)) {
            return new DispatchOutcome('PERMANENT_FAILURE', 'PERMISSION_OR_TOKEN_REJECTED', $status);
        }
        if ($status >= 400) {
            return new DispatchOutcome('PERMANENT_FAILURE', 'PROVIDER_REJECTED', $status);
        }

        // 2xx is not proof of publication unless provider-specific adapter extracts a stable receipt.
        return new DispatchOutcome('UNCERTAIN','MISSING_CONFIRMED_RECEIPT',$status);
    }
}
