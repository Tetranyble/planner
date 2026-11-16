<?php

namespace App\Domain\Ai;

/** Deterministic editorial risk hints. NEVER a truth/factuality classifier or approval. */
final class EditorialClaimScanner
{
    public const VERSION = 'editorial-risk-v1';

    public static function scan(string $copy, array $approvedClaimWordings): array
    {
        if (strlen($copy) > 50000) {
            throw new \DomainException('Copy exceeds review limit.');
        }
        $flags = [];
        if (preg_match('/\b(guarantee(?:d|s)?|risk[ -]?free|no[ -]?risk|100\s*%|best\s+in\s+the\s+world|number\s+one|#1)\b/i', $copy)) {
            $flags[] = 'ABSOLUTE_OR_UNBOUNDED_PROMISE';
        }
        if (preg_match('/(?:\b\d+(?:\.\d+)?\s*%|[$€£₦]\s*\d[\d,.]*|\b\d+(?:\.\d+)?\s*(?:million|billion)\b)/iu', $copy)) {
            $flags[] = 'NUMERIC_OR_FINANCIAL_ASSERTION';
        }
        if (preg_match('/\b(certified|licensed|regulator[ -]?approved|official partner|clinically proven)\b/i', $copy)) {
            $flags[] = 'REGULATORY_OR_AUTHORITY_CLAIM';
        }
        $matches = [];
        foreach (array_slice($approvedClaimWordings, 0, 25) as $claim) {
            if (! is_string($claim) || (function_exists('mb_strlen') ? mb_strlen(trim($claim), 'UTF-8') : strlen(trim($claim))) < 8) {
                continue;
            }
            if ((function_exists('mb_stripos') ? mb_stripos($copy, trim($claim), 0, 'UTF-8') : stripos($copy, trim($claim))) !== false) {
                $matches[] = (function_exists('mb_substr') ? mb_substr($claim, 0, 200, 'UTF-8') : substr($claim, 0, 200));
            }
        }

        // Even an exact match only means it resembles approved wording; current evidence
        // provenance, expiry and contextual claims still require independent human review.
        return ['engine_version' => self::VERSION, 'copy_hash' => hash('sha256', $copy),
            'risk_codes' => array_values(array_unique($flags)),
            'matched_approved_wording' => $matches, 'requires_human_editor' => true,
            'factual_claims_verified' => false, 'publishing_authorized' => false,
            'limitations' => 'Pattern checks cannot verify factual assertions, contextual accuracy or supporting evidence.'];
    }
}
