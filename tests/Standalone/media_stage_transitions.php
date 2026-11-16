<?php

require_once __DIR__.'/../../app/Domain/Publishing/Media/MediaStageTransition.php';
use App\Domain\Publishing\Media\MediaStageTransition as M;

$n = 0;
function check(bool $value, string $message): void
{
    global $n;
    if (! $value) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    $n++;
}
foreach (['instagram' => 'CREATE_CONTAINER', 'tiktok' => 'INITIALIZE_VIDEO', 'youtube' => 'INITIALIZE_UPLOAD_SESSION'] as $provider => $first) {
    check(M::first($provider) === $first, "first stage $provider");
}
check(M::isProbe('CHECK_CONTAINER_STATUS'), 'instagram status probe');
check(M::isProbe('FETCH_PUBLISH_STATUS'), 'tiktok status probe');
check(! M::isProbe('PUBLISH_CONTAINER'), 'publishing is not a status probe');
$cases = [
    ['instagram', 'CREATE_CONTAINER', ['state' => 'CONTAINER_CREATED', 'provider_receipt' => '123456'], 0, 'NEXT_STAGE', 'CHECK_CONTAINER_STATUS'],
    ['instagram', 'CHECK_CONTAINER_STATUS', ['state' => 'PROCESSING'], 2, 'WAITING_PROVIDER', 'CHECK_CONTAINER_STATUS'],
    ['instagram', 'CHECK_CONTAINER_STATUS', ['state' => 'PROCESSING'], 12, 'MANUAL_REQUIRED', 'CHECK_CONTAINER_STATUS'],
    ['instagram', 'CHECK_CONTAINER_STATUS', ['state' => 'READY_TO_FINALIZE'], 1, 'NEXT_STAGE', 'PUBLISH_CONTAINER'],
    ['instagram', 'PUBLISH_CONTAINER', ['state' => 'PUBLISHED', 'provider_receipt' => '234567'], 0, 'AWAITING_VERIFICATION', 'VERIFY_MEDIA_RECEIPT'],
    ['instagram', 'VERIFY_MEDIA_RECEIPT', ['state' => 'VERIFIED', 'provider_receipt' => '234567'], 1, 'CONFIRMED', 'COMPLETE'],
    ['tiktok', 'INITIALIZE_VIDEO', ['state' => 'INITIALIZED', 'provider_receipt' => 'abc_def'], 0, 'NEXT_STAGE', 'FETCH_PUBLISH_STATUS'],
    ['tiktok', 'FETCH_PUBLISH_STATUS', ['state' => 'PROCESSING'], 2, 'WAITING_PROVIDER', 'FETCH_PUBLISH_STATUS'],
    ['tiktok', 'FETCH_PUBLISH_STATUS', ['state' => 'PUBLISHED', 'provider_receipt' => 'post_id'], 4, 'AWAITING_VERIFICATION', 'VERIFY_VIDEO_RECEIPT'],
    ['tiktok', 'VERIFY_VIDEO_RECEIPT', ['state' => 'VERIFIED', 'provider_receipt' => 'post_id'], 1, 'CONFIRMED', 'COMPLETE'],
    ['instagram', 'CREATE_CONTAINER', ['state' => 'CONTAINER_CREATED', 'provider_receipt' => 'invalid ref with spaces'], 0, 'UNCERTAIN', 'CREATE_CONTAINER'],
    ['tiktok', 'INITIALIZE_VIDEO', ['state' => 'INITIALIZED'], 0, 'UNCERTAIN', 'INITIALIZE_VIDEO'],
    ['instagram', 'CREATE_CONTAINER', ['state' => 'UNCERTAIN', 'code' => 'REMOTE_RESPONSE_UNKNOWN'], 0, 'UNCERTAIN', 'CREATE_CONTAINER'],
    ['instagram', 'CREATE_CONTAINER', ['state' => 'FAILED', 'code' => 'PROVIDER_REJECTED'], 0, 'FAILED', 'CREATE_CONTAINER'],
    ['instagram', 'CREATE_CONTAINER', ['state' => 'BLOCKED', 'code' => 'MEDIA_DISPATCH_NOT_AUTHORIZED'], 0, 'HELD', 'CREATE_CONTAINER'],
    ['youtube', 'INITIALIZE_UPLOAD_SESSION', ['state' => 'UPLOAD_CONFIRMED', 'provider_receipt' => 'abc'], 0, 'UNCERTAIN', 'INITIALIZE_UPLOAD_SESSION'],
];
foreach ($cases as [$p,$op,$out,$poll,$state,$next]) {
    $result = M::next($p, $op, $out, $poll);
    check($result['state'] === $state, "$p $op state $state");
    check($result['operation'] === $next, "$p $op next $next");
    check($result['state'] !== 'PUBLISHED', "$p $op cannot self-certify delivery");
}
try {
    M::first('somewhere');
    check(false, 'unknown provider rejected');
} catch (DomainException) {
    check(true, 'unknown provider rejected');
}
echo "Media stage transition checks passed: $n assertions; no DB or provider calls.\n";
