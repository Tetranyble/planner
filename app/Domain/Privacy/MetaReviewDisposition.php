<?php

namespace App\Domain\Privacy;

use Illuminate\Validation\ValidationException;

/** Validate legal review commands independently from HTTP validation. */
final class MetaReviewDisposition
{
    public const CLEARED = 'CLEARED';

    public const RETAINED_LEGAL = 'RETAINED_LEGAL';

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function validate(array $input): array
    {
        $disposition = (string) ($input['disposition'] ?? '');
        $notes = trim((string) ($input['explanation'] ?? ''));
        $evidence = trim((string) ($input['evidence_reference'] ?? ''));
        $legalBasis = trim((string) ($input['legal_basis'] ?? ''));
        $reviewAt = ($input['review_again_at'] ?? null) ?: null;
        if (! in_array($disposition, [self::CLEARED, self::RETAINED_LEGAL], true)) {
            throw ValidationException::withMessages(['disposition' => 'Select a supported resolution.']);
        }
        if (mb_strlen($notes) < 30 || mb_strlen($notes) > 4000) {
            throw ValidationException::withMessages(['explanation' => 'Document the review in 30–4000 characters.']);
        }
        if (mb_strlen($evidence) < 8 || mb_strlen($evidence) > 512) {
            throw ValidationException::withMessages(['evidence_reference' => 'Provide a verifiable evidence reference (8–512 characters).']);
        }
        if ($disposition === self::RETAINED_LEGAL) {
            if (! in_array($legalBasis, ['LEGAL_OBLIGATION', 'LEGAL_CLAIMS', 'REGULATORY_HOLD'], true)) {
                throw ValidationException::withMessages(['legal_basis' => 'Select a documented legal basis for retention.']);
            }
            if (! is_string($reviewAt) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $reviewAt)
                || ! checkdate((int) substr($reviewAt, 5, 2), (int) substr($reviewAt, 8, 2), (int) substr($reviewAt, 0, 4))
                || $reviewAt <= now()->toDateString()) {
                throw ValidationException::withMessages(['review_again_at' => 'Choose a future date for review.']);
            }
        } elseif ($legalBasis !== '' || $reviewAt !== null) {
            throw ValidationException::withMessages(['legal_basis' => 'No retention basis is accepted for cleared records.']);
        }

        return compact('disposition', 'notes', 'evidence', 'legalBasis', 'reviewAt');
    }
}
