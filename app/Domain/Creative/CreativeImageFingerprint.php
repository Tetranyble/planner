<?php

namespace App\Domain\Creative;

/** Exact-byte, dimension and MIME comparison between generated PNG and canonical Storage. */
final class CreativeImageFingerprint
{
    public static function matches(array $ticket, array $media): bool
    {
        $expected = strtolower((string) ($ticket['sha256'] ?? ''));
        $actual = strtolower((string) ($media['sha256'] ?? ''));
        if (! preg_match('/\A[a-f0-9]{64}\z/D', $expected)
            || ! preg_match('/\A[a-f0-9]{64}\z/D', $actual)
            || ! hash_equals($expected, $actual)) {
            return false;
        }

        return (int) ($ticket['byte_size'] ?? 0) > 0
            && (int) $ticket['byte_size'] === (int) ($media['byte_size'] ?? -1)
            && (int) ($ticket['width'] ?? 0) > 0
            && (int) $ticket['width'] === (int) ($media['width'] ?? -1)
            && (int) ($ticket['height'] ?? 0) > 0
            && (int) $ticket['height'] === (int) ($media['height'] ?? -1)
            && strtolower((string) ($ticket['mime_type'] ?? '')) === 'image/png'
            && strtolower((string) ($media['mime_type'] ?? '')) === 'image/png';
    }
}
