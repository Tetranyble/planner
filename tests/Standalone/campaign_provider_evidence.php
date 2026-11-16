<?php

require dirname(__DIR__, 2).'/app/Domain/Ai/ProviderEvidenceContext.php';
require dirname(__DIR__, 2).'/app/Domain/Analytics/ProviderPerformanceSignals.php';
use App\Domain\Ai\ProviderEvidenceContext as Context;
use App\Domain\Analytics\ProviderPerformanceSignals as Trend;

$checks = 0;
$expect = function ($test, $message) use (&$checks) {
    $checks++;
    if (! $test) {
        throw new RuntimeException($message);
    }
};
$yt = Trend::evaluate([[10, 15], [30, 45], [20, 40]], false, 'youtube');
$x = Trend::evaluate([[100, 110], [200, 220], [300, 330]], false, 'x');
$expect($yt['provider'] === 'youtube', 'youtube provider identity');
$expect($x['provider'] === 'x', 'x provider identity');
$expect(! in_array('impressions', $x['not_measured'], true), 'x public impressions are recognized');
$expect(in_array('impressions', $yt['not_measured'], true), 'YT impressions are not read');
$one = Context::fromSignals(['youtube' => $yt, 'x' => $x], ['youtube']);
$expect($one['eligible'] && count($one['snapshot']['observations']) === 1, 'selected platform only');
$expect($one['snapshot']['observations'][0]['metric'] === 'video_views', 'provider-specific unit');
$two = Context::fromSignals(['youtube' => $yt, 'x' => $x], ['x', 'youtube']);
$expect($two['eligible'] && count($two['snapshot']['observations']) === 2, 'both provider signals included separately');
$expect($two['snapshot']['observations'][1]['metric'] === 'public_post_impressions', 'X public metric label');
$expect(! $two['snapshot']['publishing_authorized'], 'no automatic publication');
$expect(! $two['snapshot']['content_approval_bypassed'], 'no approval bypass');
$sorted = $two['snapshot'];
$sorted = array_reverse($sorted, true);
$expect(hash_equals(Context::digest($sorted), $two['sha256']), 'canonical digest stable after key reordering');
$insufficient = Context::fromSignals(['youtube' => Trend::evaluate([[1, 5]], false, 'youtube')], ['youtube']);
$expect(! $insufficient['eligible'], 'weak sample refused');
$truncated = Context::fromSignals(['x' => Trend::evaluate([[1, 5], [2, 8], [3, 11]], true, 'x')], ['x']);
$expect(! $truncated['eligible'], 'truncated evidence refused');
$expect(! Context::fromSignals(['youtube' => $yt],['linkedin'])['eligible'],'platform mismatch refused');
echo "AI provider evidence gate: $checks assertions PASS\n";
