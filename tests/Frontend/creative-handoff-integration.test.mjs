import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = file => readFileSync(new URL(`../../${file}`, import.meta.url), 'utf8');

test('Creative handoff is a first-party owner-bound metadata claim and canonical Storage remains uploader', () => {
    const route = read('routes/web.php');
    const model = read('app/Models/CreativeImageHandoff.php');
    const migration = read('database/migrations/2026_11_15_200000_create_creative_image_handoffs.php');
    const controller = read('app/Http/Controllers/Api/V1/FinalizeCreativeImageHandoffController.php');
    assert.match(route, /FinalizeCreativeImageHandoffController/);
    assert.match(route, /creative\/handoffs\/\{handoffUuid\}\/link/);
    assert.match(route, /kinship\.permission:media_asset\.store/);
    assert.match(route, /planner\.first_party/);
    assert.match(model, /Uuidable/);
    assert.match(migration, /actor_user_id/);
    assert.match(migration, /expires_at/);
    assert.doesNotMatch(migration, /longText|binary\(|blob\(|base64_json|provider_payload/);
    assert.doesNotMatch(controller, /Mcp|AgentDelegation|publish/);
});

test('Human operator can only link a scanned, same-byte PNG; never own an unscanned direct upload', () => {
    const service = read('app/Services/Creative/FinalizeCreativeImageHandoff.php');
    const repo = read('app/Repository/Eloquent/CreativeImageHandoffRepository.php');
    const inspector = read('app/Services/Content/StorageMediaInspector.php');
    assert.match(service, /lockForUpdate\(\)/);
    assert.match(repo, /lockOwned/);
    assert.match(repo, /where\('actor_user_id', \$actor->id\)/);
    assert.match(service, /CreativeImageFingerprint::matches/);
    assert.match(service, /\$this->storage->inspect\(\$org, \$actor, \$storageMediaId\)/);
    assert.match(service, /\$this->studio->linkMedia/);
    assert.match(service, /\$ticket->expires_at->isPast\(\)/);
    assert.match(service, /\$ticket->state === 'LINKED'/);
    assert.match(inspector, /VirusScanStatus::CLEAN/);
    assert.doesNotMatch(service, /Storage::put|file_put_contents|publish|postToSocial|armPublication/);
});

test('User interface binds private preview digest and one-time handoff to verified candidate and human review', () => {
    const imageApi = read('app/Http/Controllers/Api/V1/DownloadPrivateCreativePreviewController.php');
    const panel = read('resources/js/Pages/Organisations/Brands/Content/PrivateCreativePreviewPanel.jsx');
    const candidate = read('app/Services/Content/ListStorageMediaCandidates.php');
    assert.match(imageApi, /X-Creative-Handoff/);
    assert.match(imageApi, /no-store/);
    assert.match(panel, /x-creative-handoff/);
    assert.match(panel, /humanReviewed/);
    assert.match(panel, /Find matching verified uploads/);
    assert.match(panel, /media_asset_uuid/);
    assert.match(panel, /router\.reload\(\{ only: \['studio'\] \}\)/);
    assert.match(candidate, /'sha256'=>/);
    assert.doesNotMatch(panel, /localStorage|sessionStorage/);
});

test('Generated-media provenance must be positively disclosed in immutable draft versions', () => {
    const manager = read('app/Services/Content/ManageContentStudio.php');
    const repo = read('app/Repository/Eloquent/CreativeImageHandoffRepository.php');
    const ui = read('resources/js/Pages/Organisations/Brands/Content/Index.jsx');
    const controller = read('app/Http/Controllers/Api/V1/FinalizeCreativeImageHandoffController.php');
    assert.match(repo, /generatedMediaIds/);
    assert.match(repo, /where\('state', 'LINKED'\)/);
    assert.match(repo, /MediaSourceType::GENERATED/);
    assert.match(read('app/Services/Creative/FinalizeCreativeImageHandoff.php'), /source_type.*MediaSourceType::GENERATED/);
    assert.match(manager, /assertAiSourceDisclosure/);
    assert.match(manager, /\(\$data\['ai_generated'\]\?\?null\) !== true/);
    assert.match(ui, /ai_generated_source/);
    assert.match(controller, /'reviewed' => \['required','accepted'\]/);
});

test('Creative handoff metadata pruning cannot delete real media, alter approvals or revoke publications', () => {
    const command = read('app/Console/Commands/PruneCreativeImageHandoffs.php');
    assert.match(command, /--dry-run/);
    assert.match(command, /subDays\(7\)/);
    assert.match(command, /subDays\(90\)/);
    assert.doesNotMatch(command, /MediaAsset::|Storage::|postToSocial|approvePublication/);
});
