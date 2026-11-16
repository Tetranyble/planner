<?php

namespace App\Domain\Content;

/** Exact v1 document schema: free-form user copy is text, not opaque executable JSON. */
final class ContentVersionPayload
{
    public static function body(): array
    {
        return ['schema_version' => 1, 'format' => 'plain_text'];
    }

    /** @param list<string> $mediaUuids */
    public static function hash(string $copy, array $mediaUuids, ?bool $aiGenerated = null, ?bool $includesSensitiveClaim = null): string
    {
        $payload = [
            'schema_version' => 1, 'format' => 'plain_text',
            'copy' => $copy, 'media' => array_values($mediaUuids),
        ];
        // Backward-compatible legacy hash for unclassified content.
        // New declarations are immutable, so changing either requires a new version.
        if ($aiGenerated !== null || $includesSensitiveClaim !== null) {
            $payload['risk_declarations'] = [
                'ai_generated' => $aiGenerated,
                'includes_sensitive_claim' => $includesSensitiveClaim,
            ];
        }

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
