<?php

namespace App\Domain\Publishing\LiveContracts;

/** Raw provider data is limited to the process boundary and never persisted. */
final readonly class TextHttpResponse
{
    public function __construct(public ?int $status, public array $body, public array $headers, public bool $mayHaveSent) {}
}
