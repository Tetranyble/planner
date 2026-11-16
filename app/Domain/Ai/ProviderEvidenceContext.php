<?php

namespace App\Domain\Ai;

/** Bounded, provider-specific editorial evidence. Not causal attribution or factual certification. */
final class ProviderEvidenceContext
{
    public const VERSION = 'provider-editorial-evidence-v1';

    public static function fromSignals(array $signals, array $platforms): array
    {
        $verified = [];
        foreach (['youtube', 'x', 'facebook', 'tiktok', 'instagram'] as $provider) {
            if (! in_array($provider, $platforms, true)) {
                continue;
            }
            $trend = $signals[$provider] ?? null;
            if (! is_array($trend) || ($trend['source'] ?? '') !== 'PROVIDER_API_OBSERVED'
                || ($trend['provider'] ?? '') !== $provider
                || ! in_array($trend['status'] ?? null, ['INCREASED', 'DECREASED', 'WITHIN_TEN_PERCENT'], true)
                || ($trend['sampled_pairs'] ?? 0) < 3 || ($trend['sampled_pairs'] ?? 0) > 1000
                || ! is_array($trend['views'] ?? null)) {
                continue;
            }
            $views = $trend['views'];
            if (! is_int($views['baseline'] ?? null) || ! is_int($views['latest'] ?? null)
                || $views['baseline'] <= 0 || $views['latest'] < 0
                || $views['baseline'] > 1_000_000_000_000_000 || $views['latest'] > 1_000_000_000_000_000) {
                continue;
            }
            $verified[] = ['provider' => $provider, 'metric' => match ($provider) {
                'youtube' => 'video_views','x' => 'public_post_impressions',
                'facebook' => 'page_post_media_views', 'tiktok' => 'creator_video_views', 'instagram' => 'media_views'
            },
                'method' => 'MATCHED_POST_CUMULATIVE_COUNT', 'posts' => (int) $trend['sampled_pairs'],
                'baseline_count' => $views['baseline'], 'recent_count' => $views['latest'],
                'status' => $trend['status'], 'change_percent' => $trend['change_percent'] ?? null];
        }
        $context = ['schema_version' => self::VERSION, 'source' => 'PROVIDER_API_OBSERVED',
            'observations' => $verified,
            'limitations' => 'Cumulative matched-post snapshots, not campaign causal effect, engagement rate, unique reach, sales or ROI. Human editorial review mandatory.',
            'content_approval_bypassed' => false, 'publishing_authorized' => false];

        return ['snapshot' => $context, 'sha256' => self::digest($context),
            'eligible' => count($verified) > 0];
    }

    public static function digest(array $snapshot): string
    {
        return hash('sha256', json_encode(self::canonical($snapshot), JSON_THROW_ON_ERROR));
    }

    private static function canonical(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data, SORT_STRING);
        }
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = self::canonical($value);
            }
        }
        unset($value);

        return $data;
    }
}
