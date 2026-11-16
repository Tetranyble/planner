<?php

require dirname(__DIR__, 2).'/app/Domain/Analytics/Provider/InstagramMediaStatistics.php';
use App\Domain\Analytics\Provider\InstagramMediaStatistics as S;

$ok = 0;
function checkIG(bool $pass): void
{
    global $ok;
    if (! $pass) {
        throw new RuntimeException('Instagram metric validation failed.');
    }$ok++;
}
function rejectsIG(callable $fn): void
{
    try {
        $fn();
    } catch (DomainException) {
        checkIG(true);

        return;
    }throw new RuntimeException('Unsafe Instagram metric accepted.');
}
$media = '17918920912340654';
$owner = '17841405309211844';
$payload = ['identity' => ['id' => $media, 'owner' => ['id' => $owner], 'like_count' => 11, 'comments_count' => 4],
    'insights' => ['data' => [['name' => 'views', 'period' => 'lifetime', 'values' => [['value' => 155]]]]]];
$normalized = S::normalize($payload, $media, $owner);
checkIG($normalized['views'] === 155 && $normalized['likes'] === 11 && $normalized['comments'] === 4);
checkIG($normalized['post_id'] === $media && $normalized['account_id'] === $owner);
checkIG(hash_equals($normalized['sha256'], hash('sha256', json_encode(array_diff_key($normalized, ['sha256' => true]), JSON_THROW_ON_ERROR))));
$bad = $payload;
$bad['identity']['owner']['id'] = '18888888888888888';
rejectsIG(fn () => S::normalize($bad, $media, $owner));
$bad = $payload;
unset($bad['identity']['owner']);
rejectsIG(fn () => S::normalize($bad, $media, $owner));
$bad = $payload;
$bad['insights']['data'][0]['name'] = 'impressions';
rejectsIG(fn () => S::normalize($bad, $media, $owner));
$bad = $payload;
$bad['insights']['data'][] = $bad['insights']['data'][0];
rejectsIG(fn () => S::normalize($bad, $media, $owner));
foreach ([-1, 1.4, [], null, '01', 1000000000001] as $n) {
    $bad = $payload;
    $bad['insights']['data'][0]['values'][0]['value'] = $n;
    rejectsIG(fn () => S::normalize($bad, $media, $owner));
}
$alternative = $payload;
unset($alternative['insights']['data'][0]['values']);
$alternative['insights']['data'][0]['total_value'] = ['value' => 222];
checkIG(S::normalize($alternative, $media, $owner)['views'] === 222);
checkIG(! S::numericId('https://untrusted.test'));
echo "Instagram media metric assertions: {$ok} passed\n";
