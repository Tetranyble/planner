<?php

require_once __DIR__.'/../../app/Domain/Analytics/Provider/YouTubeVideoStatistics.php';
use App\Domain\Analytics\Provider\YouTubeVideoStatistics as Y;

$n = 0;
function checkMetric(bool $good, string $label): void
{
    global $n;
    $n++;
    if (! $good) {
        fwrite(STDERR, "FAILED $label\n");
        exit(1);
    }
}
function mustReject(callable $fn): bool
{
    try {
        $fn();

        return false;
    } catch (DomainException) {
        return true;
    }
}
$video = 'Xy123AbC_-1';
$channel = 'UC'.str_repeat('a', 22);
$valid = ['items' => [['id' => $video, 'snippet' => ['channelId' => $channel],
    'statistics' => ['viewCount' => '120', 'likeCount' => '11', 'commentCount' => '0']]]];
$data = Y::normalize($valid, $video, $channel);
checkMetric($data['views'] === 120, 'views');
checkMetric($data['likes'] === 11, 'likes');
checkMetric($data['comments'] === 0, 'comments');
checkMetric(strlen($data['sha256']) === 64, 'hash');
$unset = $valid;
unset($unset['items'][0]['statistics']['commentCount']);
checkMetric(Y::normalize($unset, $video, $channel)['comments'] === null, 'unknown comments not zero');
$cases = [
    [['items' => []], $video, $channel],
    [['items' => [$valid['items'][0], $valid['items'][0]]], $video, $channel],
    [$valid, 'javascript:', $channel],
    [$valid, $video, 'bad-channel'],
    [$valid, $video, 'UC'.str_repeat('b', 22)],
];
foreach ($cases as $i => $case) {
    checkMetric(mustReject(fn () => Y::normalize(...$case)), 'rejected '.$i);
}
foreach (['-4', '1.1', '1e12', '9999999999999', null] as $i => $bad) {
    $changed = $valid;
    $changed['items'][0]['statistics']['viewCount'] = $bad;
    checkMetric(mustReject(fn () => Y::normalize($changed, $video, $channel)), 'invalid count '.$i);
}
echo "YouTube provider statistic normalizer $n checks passed (no provider I/O).\n";
