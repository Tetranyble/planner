<?php

namespace App\Domain\Publishing\LiveContracts;

/** Offline request specification. No credential, token or HTTP client may be stored here. */
final readonly class PreparedProviderRequest
{
    public function __construct(
        public string $provider,
        public string $method,
        public string $url,
        public array $headers,
        public array $body,
        public string $receiptLocation,
    ) {
        if ($method !== 'POST') {
            throw new \InvalidArgumentException('Unexpected HTTP method');
        }
        if (! str_starts_with($url, 'https://')) {
            throw new \InvalidArgumentException('TLS required');
        }
        if (preg_match('~(?:token|secret|password|access_token)~i', json_encode($headers))) {
            throw new \InvalidArgumentException('Do not put credentials in request specifications');
        }
        if (isset($headers['Authorization'])) {
            throw new \InvalidArgumentException('Authorization belongs exclusively to the dispatch transport');
        }
    }

    /** Safe metadata only, for operator previews and diagnostic tests. */
    public function metadata(): array
    {
        return ['provider' => $this->provider, 'method' => $this->method,
            'host' => parse_url($this->url, PHP_URL_HOST),
            'path' => parse_url($this->url, PHP_URL_PATH),
            'body_keys' => array_keys($this->body),
            'receipt_location' => $this->receiptLocation,
            'dispatch_allowed' => false];
    }
}
