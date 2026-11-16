<?php

require dirname(__DIR__, 2).'/app/Domain/Analytics/Provider/TikTokVideoStatistics.php';
use App\Domain\Analytics\Provider\TikTokVideoStatistics as S;

$count = 0;
function check(bool $success): void
{
    global $count;
    if (! $success) {
        throw new RuntimeException('Failed');
    }$count++;
}
function rejects(callable $work): void
{
    try {
        $work();
    } catch (DomainException) {
        check(true);

        return;
    }throw new RuntimeException('Unexpected acceptance');
}
$video = '7077642457847991554';
$owner = 'open-user-abc';
$data = ['error' => ['code' => 'ok'], 'data' => ['videos' => [['id' => $video, 'view_count' => 110,
    'like_count' => 15, 'comment_count' => 2]]]];
$normalized = S::normalize($data, $video, $owner);
check($normalized['views'] === 110 && $normalized['likes'] === 15 && $normalized['comments'] === 2);
check($normalized['video_id'] === $video && $normalized['account_id'] === $owner);
check(hash_equals($normalized['sha256'], hash('sha256', json_encode(array_diff_key($normalized, ['sha256' => true]), JSON_THROW_ON_ERROR))));
check(S::validVideoId($video));
check(! S::validVideoId('https://evil.test'));
$bad = $data;
$bad['data']['videos'][0]['id'] = '9999999999999999999';
rejects(fn () => S::normalize($bad, $video, $owner));
$bad = $data;
$bad['error']['code'] = 'unauthorized';
rejects(fn () => S::normalize($bad, $video, $owner));
$bad = $data;
$bad['data']['videos'][] = $bad['data']['videos'][0];
rejects(fn () => S::normalize($bad, $video, $owner));
foreach ([-1, 1.5, null, '001', [], 1000000000001] as $badValue) {
    $bad = $data;
    $bad['data']['videos'][0]['view_count'] = $badValue;
    rejects(fn () => S::normalize($bad, $video, $owner));
}
rejects(fn () => S::normalize($data, $video, ''));
echo "TikTok metric assertions: {$count} passed\n";
