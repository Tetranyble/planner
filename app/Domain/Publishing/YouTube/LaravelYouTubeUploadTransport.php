<?php

namespace App\Domain\Publishing\YouTube;

use App\Domain\Publishing\Media\MediaPublicationProtocols;
use App\Models\MediaAsset;
use App\Models\Organisation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Fixed-origin Google transport; no redirects, HTTP retries or free-form remote destinations. */
final class LaravelYouTubeUploadTransport implements YouTubeUploadTransportContract
{
    public function __construct(private readonly CanonicalVideoChunkSourceContract $source) {}

    public function execute(string $action, string $token, ?string $sessionUrl, ?string $videoId,
        Organisation $organisation, MediaAsset $asset, int $offset, string $caption, string $channelId): array
    {
        if (! app()->environment('production') || ! (bool) config('planner.media_dispatch_released', false)
            || ! (bool) config('planner.media_worker_enabled', false)
            || ! (bool) config('planner.youtube_resumable_enabled', false)) {
            return ['state' => 'BLOCKED', 'code' => 'YOUTUBE_UPLOAD_RELEASE_DISABLED'];
        }
        if ($token === '' || strlen($token) > 8192 || preg_match('/[\x00\r\n]/', $token)) {
            return ['state' => 'BLOCKED', 'code' => 'YOUTUBE_TOKEN_INVALID'];
        }
        $total = (int) $asset->byte_size;
        $mime = strtolower((string) $asset->mime_type);
        ResumableUploadProtocol::assertMedia($total, $mime);
        $client = Http::withToken($token)->withOptions(['allow_redirects' => false, 'http_errors' => false])
            ->connectTimeout(5)->timeout(45);
        try {
            if ($action === 'INIT') {
                $body = ['snippet' => ['title' => MediaPublicationProtocols::youtubeTitle($caption),
                    'description' => $caption], 'status' => ['privacyStatus' => 'private']];
                $reply = $client->withHeaders(ResumableUploadProtocol::initHeaders($total, $mime))
                    ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet%2Cstatus', $body);
                if ($reply->status() !== 200) {
                    return ['state' => 'UNCERTAIN', 'code' => 'YOUTUBE_SESSION_INIT_NOT_CONFIRMED'];
                }
                $location = (string) $reply->header('Location');
                try {
                    ResumableUploadProtocol::assertSessionUrl($location);
                } catch (\DomainException) {
                    return ['state' => 'UNCERTAIN', 'code' => 'YOUTUBE_SESSION_ORIGIN_REJECTED'];
                }

                return ['state' => 'SESSION_CREATED', 'session_url' => $location];
            }
            if ($action === 'VERIFY') {
                if (! is_string($videoId) || ! preg_match('/^[A-Za-z0-9_-]{6,64}$/D', $videoId)) {
                    return ['state' => 'UNCERTAIN', 'code' => 'YOUTUBE_VIDEO_ID_INVALID'];
                }
                $reply = $client->get('https://www.googleapis.com/youtube/v3/videos', [
                    'part' => 'snippet,status,processingDetails', 'id' => $videoId]);
                if ($reply->status() !== 200) {
                    return ['state' => 'UNCERTAIN', 'code' => 'YOUTUBE_VERIFICATION_NOT_CONFIRMED'];
                }

                return ResumableUploadProtocol::verifiedVideo($videoId, $channelId,
                    is_array($reply->json()) ? $reply->json() : []);
            }
            if (! in_array($action, ['PROBE', 'CHUNK'], true) || $sessionUrl === null) {
                return ['state' => 'UNCERTAIN', 'code' => 'YOUTUBE_OPERATION_INVALID'];
            }
            ResumableUploadProtocol::assertSessionUrl($sessionUrl);
            if ($action === 'PROBE') {
                $reply = $client->withHeaders(ResumableUploadProtocol::probeHeaders($total))
                    ->send('PUT', $sessionUrl, ['body' => '']);
            } else {
                $chunk = ResumableUploadProtocol::chunk($offset, $total);
                $bytes = $this->source->read($organisation, $asset, $offset, $chunk['length']);
                if (strlen($bytes) !== $chunk['length']) {
                    return ['state' => 'BLOCKED', 'code' => 'STORAGE_CHUNK_LENGTH_MISMATCH'];
                }
                $reply = $client->withHeaders(array_merge($chunk['headers'], ['Content-Type' => $mime]))
                    ->send('PUT', $sessionUrl, ['body' => $bytes]);
            }

            return ResumableUploadProtocol::acknowledge($reply->status(),
                $reply->header('Range'), is_array($reply->json()) ? $reply->json() : null, $total);
        } catch (ConnectionException) {
            return ['state' => 'NEEDS_PROBE', 'code' => 'YOUTUBE_REMOTE_OUTCOME_UNKNOWN'];
        }
    }
}
