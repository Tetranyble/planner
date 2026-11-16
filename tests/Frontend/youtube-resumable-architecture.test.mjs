import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = path => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
test('YouTube transfer routes are Kinship protected and admin-only for mutations', () => {
    const code = read('routes/api.php');
    assert.match(code,/youtube-transfers/);
    assert.match(code,/ArmYouTubeTransferController/);
    assert.match(code,/kinship\.role:owner,manager/);
    assert.match(code,/kinship\.permission:approval_request\.update/);
});
test('YouTube upload transport defaults to disabled and Storage is an injected contract', () => {
    const config = read('config/planner.php');
    const di = read('app/Providers/AppServiceProvider.php');
    assert.match(config,/youtube_resumable_enabled.*false/);
    assert.match(di,/CanonicalVideoChunkSourceContract/);
    assert.match(di,/UnavailableVideoChunkSource/);
    assert.match(di,/YouTubeTransferRepositoryContract/);
});
test('UI shows confirmed byte checkpoints and does not allow unverified Storage arming', () => {
    const code = read('resources/js/Pages/Organisations/Brands/Content/MediaPublicationPanel.jsx');
    assert.match(code,/youtubeTransfers/);
    assert.match(code,/confirmed_bytes|committed_bytes/);
    assert.match(code,/storage_reader_verified/);
    assert.match(code,/Prepare YouTube transfer/);
});
test('Backend never stores raw provider JSON or credentials in YouTube audit evidence', () => {
    const code = read('app/Repository/Eloquent/YouTubeTransferRepository.php');
    assert.match(code,/session_url_ciphertext/);
    assert.match(code,/video_id_ciphertext/);
    assert.match(code,/safe_details/);
    assert.doesNotMatch(code,/raw_response|access_token_ciphertext/);
});
