<?php

require __DIR__.'/../../app/Domain/Ai/EditorialClaimScanner.php';
use App\Domain\Ai\EditorialClaimScanner as S;

$n = 0;
function verify(bool $value): void
{
    global $n;
    $n++;
    if (! $value) {
        throw new RuntimeException('Check '.$n.' failed');
    }
}
$a = S::scan('Guaranteed 100% returns and official partner!', ['Approved plain wording']);
verify(in_array('ABSOLUTE_OR_UNBOUNDED_PROMISE', $a['risk_codes'], true));
verify(in_array('NUMERIC_OR_FINANCIAL_ASSERTION', $a['risk_codes'], true));
verify(in_array('REGULATORY_OR_AUTHORITY_CLAIM', $a['risk_codes'], true));
verify($a['factual_claims_verified'] === false && $a['publishing_authorized'] === false);
$b = S::scan('Approved plain wording', ['Approved plain wording', 'Draft unapproved claim']);
verify(count($b['matched_approved_wording']) === 1);
verify($b['requires_human_editor'] === true);
verify($b['risk_codes'] === []);
verify($b['copy_hash'] === hash('sha256', 'Approved plain wording'));
echo "AI editorial risk scanner: {$n} assertions passed\n";
