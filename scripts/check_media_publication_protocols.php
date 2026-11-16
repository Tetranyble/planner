<?php

require_once __DIR__.'/../app/Domain/Publishing/Media/MediaPublicationProtocols.php';
use App\Domain\Publishing\Media\MediaPublicationProtocols as P;
use App\Domain\Publishing\Media\MediaReleaseRequirements;

$checks = 0;
function ok(bool $truth, string $message): void
{
    global $checks;
    $checks++;
    if (! $truth) {
        fwrite(STDERR, $message."\n");
        exit(1);
    }
}
function rejects(callable $fn): bool
{
    try {
        $fn();

        return false;
    } catch (DomainException) {
        return true;
    }
}
foreach (['instagram' => 'image/jpeg', 'tiktok' => 'video/mp4', 'youtube' => 'video/webm'] as $provider => $mime) {
    $plan = P::plan($provider, $mime);
    ok($plan['automatic_retry_after_send'] === false, $provider.' replays must be disabled');
    ok(count($plan['stages']) >= 4, $provider.' must have a multi-step protocol');
    ok($plan['requires_explicit_publish_capability'] === true, 'explicit entitlement required');
    ok(P::validateAsset($provider, $mime, 12345, 1) === [], 'valid media rejected');
    ok(P::validateAsset($provider, $mime, 12345, 2) !== [], 'multi-media allowed unexpectedly');
    ok(P::validateAsset($provider, 'application/x-sh', 12345, 1) !== [], 'script media allowed');
}
foreach (['http://cdn.example.com/a', 'https://127.0.0.1/a', 'https://evil.example.com/a',
    'https://user:pass@cdn.example.com/a', 'https://cdn.example.com:8443/a'] as $url) {
    ok(rejects(fn () => P::assertOrigin($url, ['cdn.example.com'])), 'unsafe origin accepted');
}
P::assertOrigin('https://cdn.example.com/image.jpg', ['cdn.example.com']);
ok(true, 'approved origin');
$ig = P::initRequest('instagram', '123456789', 'image/jpeg', 'New collection',
    'https://cdn.example.com/photo.jpg', ['cdn.example.com']);
ok($ig['path'] === '/{graph_version}/123456789/media', 'Instagram endpoint');
ok(isset($ig['body']['image_url']), 'Instagram image URL');
ok(! isset($ig['body']['access_token']), 'Instagram secret leaked');
$reel = P::initRequest('instagram', '123456789', 'video/mp4', 'New reel',
    'https://cdn.example.com/reel.mp4', ['cdn.example.com']);
ok($reel['body']['media_type'] === 'REELS', 'Instagram reel shape');
ok(rejects(fn () => P::initRequest('tiktok', 'creator', 'video/mp4', 'Video',
    'https://cdn.example.com/video.mp4', ['cdn.example.com'])), 'TikTok consent skipped');
$tiktok = P::initRequest('tiktok', 'creator', 'video/mp4', 'Video',
    'https://cdn.example.com/video.mp4', ['cdn.example.com'], [
        'creator_options_checked' => true, 'explicit_consent' => true, 'privacy_level' => 'SELF_ONLY',
        'allowed_privacy_levels' => ['SELF_ONLY', 'PUBLIC_TO_EVERYONE']]);
ok($tiktok['path'] === '/v2/post/publish/video/init/', 'TikTok endpoint');
ok($tiktok['body']['source_info']['source'] === 'PULL_FROM_URL', 'TikTok source');
ok($tiktok['body']['post_info']['privacy_level'] === 'SELF_ONLY', 'TikTok privacy');
$youtube = P::initRequest('youtube', 'creator', 'video/mp4', 'Release\nDescription');
ok($youtube['query']['uploadType'] === 'resumable', 'YouTube resumable');
ok($youtube['body']['status']['privacyStatus'] === 'private', 'YouTube privacy conservative');
ok(! isset($youtube['body']['status']['selfDeclaredMadeForKids']), 'YouTube children decision invented');
foreach ([
    ['instagram', 'CREATE_CONTAINER', ['id' => '178123456789'], 'CONTAINER_CREATED'],
    ['instagram', 'CHECK_CONTAINER_STATUS', ['status_code' => 'FINISHED'], 'READY_TO_FINALIZE'],
    ['instagram', 'CHECK_CONTAINER_STATUS', ['status_code' => 'ERROR'], 'FAILED'],
    ['instagram', 'PUBLISH_CONTAINER', ['id' => '178123456789'], 'PUBLISHED'],
    ['instagram', 'PUBLISH_CONTAINER', [], 'UNCERTAIN'],
    ['tiktok', 'INITIALIZE_VIDEO', ['error' => ['code' => 'ok'], 'data' => ['publish_id' => 'v_pub~123']], 'INITIALIZED'],
    ['tiktok', 'FETCH_PUBLISH_STATUS', ['data' => ['status' => 'PROCESSING_DOWNLOAD']], 'PROCESSING'],
    ['tiktok', 'FETCH_PUBLISH_STATUS', ['data' => ['status' => 'PUBLISH_COMPLETE']], 'UNCERTAIN'],
    ['tiktok', 'FETCH_PUBLISH_STATUS', ['data' => ['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => [123456789]]], 'PUBLISHED'],
    ['youtube', 'UPLOAD_COMPLETE', ['id' => 'abc123def'], 'UPLOAD_CONFIRMED'],
    ['youtube', 'UPLOAD_COMPLETE', [], 'UNCERTAIN'],
] as [$provider,$operation,$reply,$expected]) {
    ok(P::providerState($provider, $operation, $reply)['state'] === $expected, "$provider $operation expected $expected");
}
$status = P::statusRequest('instagram', '1780000000000');
ok($status['method'] === 'GET' && $status['query']['fields'] === 'status_code', 'Instagram status read-only');
$status = P::statusRequest('tiktok', 'v_pub~xyz');
ok($status['body']['publish_id'] === 'v_pub~xyz', 'TikTok status reference');
ok(rejects(fn () => P::statusRequest('instagram', 'abc/../foo')), 'status path traversal accepted');
$final = P::finalizeInstagram('123456789', '178123456789');
ok($final['body']['creation_id'] === '178123456789', 'Instagram creation id mismatch');
ok(rejects(fn () => P::finalizeInstagram('abc', '178123456789')), 'Invalid Instagram actor accepted');
require_once __DIR__.'/../app/Domain/Publishing/Media/MediaReleaseRequirements.php';
$requirements = MediaReleaseRequirements::checklist('tiktok', true, true, true);
ok(! $requirements['can_publish'],'Unimplemented live media worker cannot be enabled via config');
ok(in_array('REMOTE_MEDIA_RECEIPT_VERIFICATION_NOT_RELEASED',$requirements['release_blockers'],true),'Provider verification gate omitted');
ok(in_array('TETRANYBLE_DISTRIBUTION_ADAPTER_UNVERIFIED',$requirements['release_blockers'],true),'Canonical Storage adapter gate omitted');
echo "Media provider protocol safety checks passed ($checks assertions).\n";
