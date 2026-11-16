<?php

namespace App\Domain\Ai;

/** Provider-observed token counters only. Missing or malformed values stay UNKNOWN. */
final class ProviderTokenEvidence
{
    /** @return array{input_tokens: ?int, output_tokens: ?int} */
    public static function fromResponse(array $data): array
    {
        $usage = $data['usage'] ?? null;
        if (! is_array($usage)) {
            return ['input_tokens' => null, 'output_tokens' => null];
        }

        return [
            'input_tokens' => self::counter($usage['input_tokens'] ?? null),
            'output_tokens' => self::counter($usage['output_tokens'] ?? null),
        ];
    }

    private static function counter(mixed $value): ?int
    {
        if (! is_int($value) || $value < 0 || $value > 20_000_000) {
            return null;
        }

        return $value;
    }
}
