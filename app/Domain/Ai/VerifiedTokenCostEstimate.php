<?php

namespace App\Domain\Ai;

/** Calculations are in integer micro-USD; *rates* are supplied by the operator, never guessed. */
final class VerifiedTokenCostEstimate
{
    public static function calculate(?string $model, ?array $usage, array $rates, string $revision): ?array
    {
        if ($model === null || $model === '' || $revision === '' || strlen($revision) > 64
            || ! preg_match('/^[A-Za-z0-9._-]+$/D', $revision)
            || ! is_array($usage) || ! isset($rates[$model]) || ! is_array($rates[$model])) {
            return null;
        }
        $input = $usage['input_tokens'] ?? null;
        $output = $usage['output_tokens'] ?? null;
        $inRate = $rates[$model]['input_micro_usd_per_million'] ?? null;
        $outRate = $rates[$model]['output_micro_usd_per_million'] ?? null;
        foreach ([$input, $output, $inRate, $outRate] as $n) {
            // Money must never be based on coerced, fractional, or negative provider evidence.
            if (! is_int($n) || $n < 0 || $n > 100000000000) {
                return null;
            }
        }
        // Deliberate bound on products; fail closed rather than silently overflow PHP int.
        if ($input > 0 && $inRate > intdiv(PHP_INT_MAX, $input)) {
            return null;
        }
        if ($output > 0 && $outRate > intdiv(PHP_INT_MAX, $output)) {
            return null;
        }
        $inputComponent = $input * $inRate;
        $outputComponent = $output * $outRate;
        if ($outputComponent > PHP_INT_MAX - 999999 - $inputComponent) {
            return null;
        }
        $weighted = $inputComponent + $outputComponent;

        return [
            'micro_usd' => (int) intdiv($weighted + 999999, 1000000),
            'revision' => $revision,
            'source' => 'operator_configured_token_rates_not_provider_invoice',
        ];
    }

    public static function configured(): array
    {
        $raw = config('services.planner_ai.cost_rates_json', '');
        if (! is_string($raw) || strlen($raw) > 10000) {
            return [];
        }
        $rates = json_decode($raw, true);

        return is_array($rates) ? $rates : [];
    }
}
