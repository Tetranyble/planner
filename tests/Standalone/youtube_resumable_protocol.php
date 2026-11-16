<?php

require __DIR__.'/../../app/Domain/Publishing/YouTube/ResumableUploadProtocol.php';
use App\Domain\Publishing\YouTube\ResumableUploadProtocol as P;

$n = 0;
function check(bool $ok): void
{
    global $n;
    $n++;
    if (! $ok) {
        throw new RuntimeException('Assertion #'.$n.' failed');
    }
}
function rejected(callable $action): void
{
    try {
        $action();
    } catch (DomainException) {
        check(true);

        return;
    } check(false);
}
$session = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet%2Cstatus&upload_id=8_xYabcde22';
P::assertSessionUrl($session);
check(true);
foreach (['https://evil.example/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status&upload_id=8_xYabcde22',
    'https://www.googleapis.com.evil.example/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status&upload_id=8_xYabcde22',
    'https://www.googleapis.com:8443/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status&upload_id=8_xYabcde22',
    $session.'&upload_id=another123', $session.'&redirect=https://evil.example',
    'http://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status&upload_id=8_xYabcde22'] as $bad) {
    rejected(fn () => P::assertSessionUrl($bad));
}
check(P::chunk(0, 10000000)['length'] === 8388608);
check(P::chunk(8388608, 10000000)['length'] === 1611392);
check(P::chunk(8388608, 10000000)['headers']['Content-Range'] === 'bytes 8388608-9999999/10000000');
rejected(fn () => P::chunk(-1, 100));
rejected(fn () => P::chunk(100, 100));
check(P::probeHeaders(100)['Content-Range'] === 'bytes */100');
check(P::acknowledge(308, 'bytes=0-999', null, 2000)['offset'] === 1000);
check(P::acknowledge(308, null, null, 2000)['offset'] === 0);
check(P::acknowledge(308, 'bytes=2-9', null, 2000)['state'] === 'UNCERTAIN');
check(P::acknowledge(308, 'bytes=0-2000', null, 2000)['state'] === 'UNCERTAIN');
check(P::acknowledge(503, null, null, 2000)['state'] === 'NEEDS_PROBE');
check(P::acknowledge(201, null, ['id' => 'uBc_def98'], 2000)['state'] === 'UPLOADED');
check(P::acknowledge(201, null, ['foo' => 'bar'], 2000)['state'] === 'UNCERTAIN');
check(P::acknowledge(410, null, null, 2000)['state'] === 'MANUAL_REQUIRED');
$video = ['items' => [['id' => 'uBc_def98', 'snippet' => ['channelId' => 'channelXYZ'],
    'status' => ['uploadStatus' => 'processed'], 'processingDetails' => ['processingStatus' => 'succeeded']]]];
check(P::verifiedVideo('uBc_def98', 'channelXYZ', $video)['state'] === 'VERIFIED');
check(P::verifiedVideo('uBc_def98', 'other', $video)['state'] === 'UNCERTAIN');
$video['items'][0]['processingDetails']['processingStatus'] = 'processing';
check(P::verifiedVideo('uBc_def98', 'channelXYZ', $video)['state'] === 'PROCESSING');
$video['items'][0]['status']['uploadStatus'] = 'failed';
check(P::verifiedVideo('uBc_def98','channelXYZ',$video)['state'] === 'FAILED');
echo "YouTube resumable safety: {$n} assertions passed\n";
