import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = file => readFileSync(new URL(`../../${file}`, import.meta.url), 'utf8');

test('media preparation has organisation/brand scope and a separate repository binding', () => {
    const source = read('app/Repository/Eloquent/MediaPublicationPreparationRepository.php');
    assert.match(source, /where\('organisation_id'/);
    assert.match(source, /where\('brand_id'/);
    assert.match(source, /lockForUpdate/);
    assert.match(read('app/Providers/AppServiceProvider.php'), /MediaPublicationPreparationRepositoryContract::class/);
});
test('preparation cannot trigger provider HTTP, posting workers or mutate publication jobs', () => {
    const source = read('app/Services/Publishing/PrepareMediaPublications.php');
    assert.doesNotMatch(source, /Http::|->send\(|->dispatch\(|->publish\(|startAttempt\(/);
    assert.match(source, /StorageMediaInspector/);
    assert.match(source, /media_sha256/);
    assert.match(source, /remote_dispatch_allowed/);
    assert.match(source, /APPROVAL/ig);
});
test('routes enforce Kinship and invokable command convention', () => {
    const routes=read('routes/api.php');
    assert.match(routes, /media-publication-preparations/);
    assert.match(routes, /kinship\.permission:approval_request\.update/);
    const command=read('app/Http/Controllers/Api/V1/AbortMediaPublicationPreparationController.php');
    assert.match(command,/function __invoke/);
    assert.doesNotMatch(command,/function (?:index|store|update|destroy)\(/);
});
test('media preparation React UI acknowledges that dispatch is disabled', () => {
    const src=read('resources/js/Pages/Organisations/Brands/Content/MediaPublicationPanel.jsx');
    assert.match(src,/remote|never sends/i);
    assert.match(src,/expected_version/);
    assert.match(src,/media-publication-release/);
    assert.match(src,/aria-label/);
    assert.match(read('resources/js/Pages/Organisations/Brands/Content/PublicationIntentsPanel.jsx'),/MediaPublicationPanel/);
});
test('media protocol receipts are conservative and no auto replay is allowed', () => {
    const src=read('app/Domain/Publishing/Media/MediaPublicationProtocols.php');
    assert.match(src,/automatic_retry_after_send/);
    assert.match(src,/MISSING_VALID_PROVIDER_RECEIPT/);
    assert.match(src,/UNCERTAIN/);
    assert.match(src,/creator_options_checked/);
    assert.match(src,/privacyStatus'\s*=>\s*'private'/);
});

test('all media release gates stay blocked independently of configuration flags', () => {
    const src=read('app/Domain/Publishing/Media/MediaReleaseRequirements.php');
    assert.match(src,/REMOTE_MEDIA_RECEIPT_VERIFICATION_NOT_RELEASED/);
    assert.match(src,/YOUTUBE_RESUMABLE_STORAGE_STREAM_UNVERIFIED/);
    assert.match(src,/can_publish'\s*=>\s*false/);
    assert.match(read('config/planner.php'),/PLANNER_MEDIA_DISPATCH_RELEASED.*false/);
});

test('guarded media transport pins provider destinations and remains disabled without release', () => {
    const src=read('app/Domain/Publishing/Media/MediaStepEgressPolicy.php');
    assert.match(src,/graph\.facebook\.com/);
    assert.match(src,/open\.tiktokapis\.com/);
    assert.match(src,/www\.googleapis\.com/);
    assert.match(read('app/Domain/Publishing/Media/GuardedMediaStep.php'),/YOUTUBE_RESUMABLE_STREAM_NOT_AVAILABLE/);
    assert.match(read('app/Domain/Publishing/Media/LaravelMediaStepTransport.php'),/media_dispatch_released/);
});
