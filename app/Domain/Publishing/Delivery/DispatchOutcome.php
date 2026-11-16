<?php

namespace App\Domain\Publishing\Delivery;

/** No raw response body, credential, URL, customer data or arbitrary provider headers. */
final readonly class DispatchOutcome
{
    private const KINDS = ['SUCCESS', 'PERMANENT_FAILURE', 'RETRYABLE', 'RATE_LIMITED', 'UNCERTAIN'];

    public function __construct(
        public string $kind,
        public string $code,
        public ?int $httpStatus = null,
        public ?int $retryAfterSeconds = null,
        public ?string $providerReceipt = null,
    ) {
        if (! in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException('Invalid outcome');
        }
        if (! preg_match('/^[A-Z0-9_]{2,60}$/', $code)) {
            throw new \InvalidArgumentException('Invalid outcome code');
        }
        if ($httpStatus !== null && ($httpStatus < 100 || $httpStatus > 599)) {
            throw new \InvalidArgumentException('Invalid HTTP status');
        }
        if ($retryAfterSeconds !== null && ($retryAfterSeconds < 0 || $retryAfterSeconds > 86400)) {
            throw new \InvalidArgumentException('Invalid retry delay');
        }
        if ($kind === 'SUCCESS' && (! is_string($providerReceipt) || ! preg_match('/^[a-zA-Z0-9:_-]{3,160}$/D', $providerReceipt))) {
            throw new \InvalidArgumentException('Successful remote delivery requires a verified-format receipt');
        }
        if ($kind !== 'SUCCESS' && $providerReceipt !== null) {
            throw new \InvalidArgumentException('Non-success cannot claim receipt');
        }
    }

    public function redacted(): array
    {
        return ['kind' => $this->kind, 'code' => $this->code, 'http_status' => $this->httpStatus,
            'retry_after_seconds' => $this->retryAfterSeconds, 'has_provider_receipt' => $this->providerReceipt !== null];
    }
}
