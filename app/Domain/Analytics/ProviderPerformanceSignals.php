<?php

namespace App\Domain\Analytics;

/** Deterministic, provenance-aware signals. Never attributes causality, ROI or paid reach. */
final class ProviderPerformanceSignals
{
    public static function evaluate(array $pairs, bool $truncated = false, string $provider = 'youtube'): array
    {
        if (! in_array($provider, ['youtube', 'x', 'facebook', 'tiktok', 'instagram'], true)) {
            throw new \DomainException('Unsupported trend provider.');
        }
        $metric = match ($provider) {
            'youtube' => 'video views','x' => 'public X post impressions',
            'facebook' => 'Facebook Page post media views', 'tiktok' => 'TikTok creator video views', 'instagram' => 'Instagram media views',
        };
        $noun = $provider === 'youtube' ? 'videos' : 'posts';
        $base = ['source' => 'PROVIDER_API_OBSERVED', 'provider' => $provider,
            'method' => $provider === 'youtube' ? 'SAME_VIDEO_CUMULATIVE_COUNT_COMPARISON' : 'SAME_POST_CUMULATIVE_COUNT_COMPARISON',
            'baseline_days' => 14, 'sampled_pairs' => count($pairs),
            'not_measured' => $provider === 'youtube' ? ['impressions', 'unique_reach', 'clicks', 'conversions', 'revenue', 'roi'] : ['unique_reach', 'clicks', 'conversions', 'revenue', 'roi']];
        if ($truncated) {
            return $base + [
                'status' => 'TRUNCATED', 'views' => null, 'change_percent' => null,
                'recommendation' => 'Narrow the sample or increase the approved processing limit; no complete insight is available.'];
        }
        if (count($pairs) < 3) {
            return $base + [
                'status' => 'INSUFFICIENT_BASELINES', 'views' => null, 'change_percent' => null,
                'recommendation' => 'Collect at least three matched '.$noun.' with observations on both sides of the 14-day cutoff.'];
        }
        $before = 0;
        $after = 0;
        foreach ($pairs as $pair) {
            if (! is_array($pair) || count($pair) !== 2 || ! is_int($pair[0]) || ! is_int($pair[1])
                || $pair[0] < 0 || $pair[1] < 0 || $pair[0] > 1_000_000_000_000
                || $pair[1] > 1_000_000_000_000) {
                throw new \DomainException('Invalid provider analytics pair.');
            }
            $before += $pair[0];
            $after += $pair[1];
        }
        $pct = $before > 0 ? round(100 * ($after - $before) / $before, 1) : null;
        $status = $pct === null ? 'BASELINE_ZERO' : ($pct > 10 ? 'INCREASED' : ($pct < -10 ? 'DECREASED' : 'WITHIN_TEN_PERCENT'));
        $recommendation = match ($status) {
            'INCREASED' => ucfirst($metric).' increased across the same matched '.$noun.'. Review distribution context before changing strategy.',
            'DECREASED' => ucfirst($metric).' decreased or counts were revised. Check provider reporting before drawing conclusions.',
            'WITHIN_TEN_PERCENT' => 'Aggregate '.$metric.' stayed within a 10% band. Continue monitoring matched '.$noun.'.',
            default => 'Earlier '.$metric.' were zero. Collect additional baseline evidence before interpreting relative change.',
        };

        return $base + ['status' => $status, 'views' => ['baseline' => $before, 'latest' => $after,
            'change' => $after - $before], 'change_percent' => $pct, 'recommendation' => $recommendation,
            'caveat' => 'Cumulative '.$metric.', not daily growth, unique reach, ROI or a causal campaign lift estimate.'];
    }
}
