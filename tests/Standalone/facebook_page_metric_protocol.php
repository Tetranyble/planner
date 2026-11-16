<?php

require_once dirname(__DIR__, 2).'/app/Domain/Analytics/Provider/FacebookPagePostStatistics.php';
use App\Domain\Analytics\Provider\FacebookPagePostStatistics as S;

$assertions = 0;
function check(bool $truth): void
{
    global $assertions;
    if (! $truth) {
        throw new RuntimeException('Facebook protocol invariant failed.');
    } $assertions++;
}
function mustReject(callable $action): void
{
    try {
        $action();
    } catch (DomainException) {
        check(true);

        return;
    }
    throw new RuntimeException('Unsafe metric accepted.');
}
$page = '123456789';
$post = '123456789_987654321';
$payload = ['identity' => ['id' => $post, 'from' => ['id' => $page]], 'insights' => ['data' => [
    ['name' => 'post_media_view', 'period' => 'lifetime', 'values' => [['value' => 145]]],
]]];
$normalized = S::normalize($payload, $post, $page);
check($normalized['views'] === 145);
check($normalized['likes'] === null && $normalized['comments'] === null);
check($normalized['account_id'] === $page);
check(hash_equals($normalized['sha256'], hash('sha256', json_encode(array_diff_key($normalized, ['sha256' => true]), JSON_THROW_ON_ERROR))));
check(S::validPostId($post, $page));
check(! S::validPostId('https://evil.test', $page));
check(! S::validPostId('999_987654321', $page));
$foreign = $payload;
$foreign['identity']['from']['id'] = '88888888';
mustReject(fn () => S::normalize($foreign, $post, $page));
$foreign = $payload;
$foreign['identity']['id'] = '123456789_111111111';
mustReject(fn () => S::normalize($foreign, $post, $page));
foreach ([-1, 1.5, '01', null, [], 1000000000001] as $value) {
    $bad = $payload;
    $bad['insights']['data'][0]['values'][0]['value'] = $value;
    mustReject(fn () => S::normalize($bad, $post, $page));
}
foreach (['post_impressions', 'page_media_view', 'post_clicks'] as $name) {
    $bad = $payload;
    $bad['insights']['data'][0]['name'] = $name;
    mustReject(fn () => S::normalize($bad, $post, $page));
}
$bad = $payload;
$bad['insights']['data'][] = $bad['insights']['data'][0];
mustReject(fn () => S::normalize($bad, $post, $page));
$bad = $payload;
$bad['insights']['data'][0]['values'] = [];
mustReject(fn () => S::normalize($bad, $post, $page));
$bad = $payload;
$bad['insights']['data'][0]['period'] = 'day';
mustReject(fn () => S::normalize($bad, $post, $page));
echo "Facebook Page metric assertions: {$assertions} passed\n";
