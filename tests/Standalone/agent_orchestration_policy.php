<?php

require __DIR__.'/../../app/Domain/Agent/AgentDelegationPolicy.php';
use App\Domain\Agent\AgentDelegationPolicy as P;

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }$checks++;
};
$check(P::validMode(P::REVIEW_REQUIRED), 'Review mode must be valid');
$check(P::validMode(P::DELEGATED_PUBLISH), 'Delegated mode must be valid');
$check(! P::validMode('UNLIMITED'), 'Unknown modes must fail closed');
foreach (['research.capture', 'media.link', 'content.draft', 'content.target', 'approval.request', 'publication.plan'] as $action) {
    $check(P::permits(P::REVIEW_REQUIRED, $action), 'Review mode should allow '.$action);
    $check(P::permits(P::DELEGATED_PUBLISH, $action), 'Delegated mode should allow '.$action);
}
$check(! P::permits(P::REVIEW_REQUIRED, 'publication.arm'), 'Review mode must never arm');
$check(P::permits(P::DELEGATED_PUBLISH, 'publication.arm'), 'Explicit delegation can arm');
$check(! P::permits(P::DELEGATED_PUBLISH, 'approval.decide'), 'Agents must not approve');
$check(! P::permits(P::DELEGATED_PUBLISH, 'publish.unreviewed'), 'No bypass');
$check(! P::permits('UNLIMITED', 'content.draft'), 'Unknown mode must fail closed');
$check(P::canonicalHash('content.draft', ['b' => 2, 'a' => 1]) === P::canonicalHash('content.draft', ['a' => 1, 'b' => 2]), 'Canonical idempotency hash must ignore object key order');
$check(P::canonicalHash('content.draft', ['a' => 1]) !== P::canonicalHash('content.draft', ['a' => 2]), 'Changed input must change hash');
$check(P::canonicalHash('content.draft', ['a' => 1]) !== P::canonicalHash('content.target', ['a' => 1]), 'Action must be bound to hash');
$check(count(P::sources([['url' => 'https://example.org/study', 'title' => 'Study', 'note' => 'Unverified']])) === 1, 'Source capture');
foreach (['http://example.org/post', 'https://user:pass@example.org/private', 'javascript:alert(1)', 'not-a-url'] as $unsafe) {
    try {
        P::sources([['url' => $unsafe]]);
        $rejected = false;
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    $check($rejected, 'Unsafe source rejected: '.$unsafe);
}
try {
    P::sources(array_fill(0, 13, ['url' => 'https://example.org']));
    $rejected = false;
} catch (InvalidArgumentException) {
    $rejected = true;
}
$check($rejected,'Source count cap');
echo "PASS {$checks} assertions: agent delegation and evidence policy\n";
