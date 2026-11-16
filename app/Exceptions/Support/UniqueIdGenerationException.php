<?php

namespace App\Exceptions\Support;

use RuntimeException;

class UniqueIdGenerationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $prefix,
        public readonly int $maxAttempts,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function afterAttempts(string $prefix, int $maxAttempts): self
    {
        return new self(
            "Unable to generate a unique account id with prefix '{$prefix}' after {$maxAttempts} attempts.",
            $prefix,
            $maxAttempts,
        );
    }
}
