<?php

require dirname(__DIR__, 2).'/app/Domain/Analytics/Provider/XPostStatistics.php';
use App\Domain\Analytics\Provider\XPostStatistics as X;

$checks = 0;
$assert = function (bool $condition, string $message) use (&$checks) {
    $checks++;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$post = '1923456789012345678';
$actor = '12345678901234567';
$payload = ['data' => ['id' => $post, 'author_id' => $actor, 'public_metrics' => [
    'impression_count' => 451, 'like_count' => 17, 'reply_count' => null, 'retweet_count' => 4]]];
$normal = X::normalize($payload, $post, $actor);
$assert($normal['views'] === 451, 'views');
$assert($normal['likes'] === 17, 'likes');
$assert($normal['comments'] === null, 'unknown replies');
$assert($normal['sha256'] === hash('sha256', json_encode(array_diff_key($normal, ['sha256' => true]), JSON_THROW_ON_ERROR)), 'hash');
$assert(X::snowflake($post), 'valid snowflake');
$assert(! X::snowflake('1e21'), 'reject exponent notation');
$assert(! X::snowflake('00'), 'reject leading zero');
$assert(! X::snowflake(''), 'reject missing ID');
$invalid = [
    [$payload, $post, '7654321'],
    [['data' => ['id' => $post, 'author_id' => '999', 'public_metrics' => ['like_count' => 3]]], $post, $actor],
    [['data' => ['id' => $post, 'author_id' => $actor, 'public_metrics' => []]], $post, $actor],
    [['data' => ['id' => $post, 'author_id' => $actor, 'public_metrics' => ['like_count' => -1]]], $post, $actor],
    [['data' => ['id' => $post, 'author_id' => $actor, 'public_metrics' => ['like_count' => 1.5]]], $post, $actor],
    [['data' => ['id' => $post, 'author_id' => $actor, 'public_metrics' => ['like_count' => '2e6']]], $post, $actor],
    [['errors' => [['detail' => 'not found']]], $post, $actor],
];
foreach ($invalid as [$data,$expectedPost,$expectedActor]) {
    $throws = false;
    try {
        X::normalize($data, $expectedPost, $expectedActor);
    } catch (DomainException) {
        $throws = true;
    }
    $assert($throws, 'invalid identity or metrics must fail');
}
$assert(X::normalize(['data' => ['id' => $post, 'author_id' => $actor, 'public_metrics' => ['like_count' => 0]]], $post, $actor)['likes'] === 0, 'real zero');
echo "X read-only provider metric normalizer: $checks assertions PASS\n";
