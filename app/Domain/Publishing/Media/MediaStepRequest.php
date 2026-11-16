<?php

namespace App\Domain\Publishing\Media;

/** Ephemeral media request: never persist this object, its URL or signed source links. */
final readonly class MediaStepRequest
{
    public function __construct(
        public string $provider,
        public string $operation,
        public string $method,
        public string $url,
        public array $body = [],
        public array $headers = [],
    ) {
        MediaStepEgressPolicy::assertAllowed($this);
    }

    public function safeMetadata(): array
    {
        return ['provider' => $this->provider, 'operation' => $this->operation, 'method' => $this->method,
            'host' => parse_url($this->url, PHP_URL_HOST), 'path' => parse_url($this->url, PHP_URL_PATH),
            'body_keys' => array_keys($this->body), 'sent' => false];
    }
}
