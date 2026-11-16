<?php

namespace App\Domain\Content;

/** Conservative editorial checks. Never represent API validation or publishing permission. */
final class PlatformVariantRules
{
    public const PROVIDERS = ['facebook', 'instagram', 'linkedin', 'x', 'tiktok', 'youtube'];

    // Editorial limits, NOT assertions of every provider endpoint's accepted limits.
    private const CAPTION_LIMITS = [
        'facebook' => 5000, 'instagram' => 2200, 'linkedin' => 3000,
        'x' => 280, 'tiktok' => 2200, 'youtube' => 5000,
    ];

    public static function inspect(string $provider, string $caption): array
    {
        $provider = strtolower(trim($provider));
        $errors = [];
        $length = preg_match_all('/./us', $caption, $unused);
        if ($length === false) {
            $length = 0;
        }
        if (preg_match('//u', $caption) !== 1) {
            $errors[] = 'INVALID_UNICODE';
        }
        if (! in_array($provider, self::PROVIDERS, true)) {
            $errors[] = 'UNKNOWN_PLATFORM';
        }
        if (trim($caption) === '') {
            $errors[] = 'EMPTY_CAPTION';
        }
        if ($length > (self::CAPTION_LIMITS[$provider] ?? 0)) {
            $errors[] = 'EDITORIAL_CAPTION_LIMIT';
        }
        if (str_contains($caption, "\0")) {
            $errors[] = 'INVALID_CONTROL_CHARACTER';
        }

        return [
            'provider' => $provider,
            'characters' => $length,
            'editorial_limit' => self::CAPTION_LIMITS[$provider] ?? null,
            'issues' => $errors,
            'editorially_valid' => count($errors) === 0,
            'remote_publishable' => false,
            'note' => 'Editorial preflight only. Verify API scopes, media, claims and live platform requirements separately.',
        ];
    }

    public static function inspectBatch(array $variants): array
    {
        return array_map(static fn (array $row) => self::inspect((string) ($row['provider'] ?? ''), (string) ($row['platform_caption'] ?? '')), $variants);
    }
}
