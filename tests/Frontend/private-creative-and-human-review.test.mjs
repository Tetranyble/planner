import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = file => readFileSync(new URL(`../../${file}`, import.meta.url), 'utf8');

test('Creative Studio generates one private original image, disabled by default', () => {
    const api = read('app/Services/Creative/GeneratePrivateCreativePreview.php');
    const config = read('config/planner.php');
    const routes = read('routes/web.php');
    assert.match(config, /PLANNER_CREATIVE_IMAGE_ENABLED.*false/);
    assert.match(api, /api\.openai\.com\/v1\/images\/generations/);
    assert.match(api, /'n'\s*=>\s*1/);
    assert.match(api, /b64_json/);
    assert.match(api, /getimagesizefromstring/);
    assert.match(routes, /DownloadPrivateCreativePreviewController/);
    assert.match(routes, /kinship\.permission:media_asset\.store/);
    assert.match(routes, /planner\.first_party/);
    assert.match(routes, /throttle:2,1/);
});

test('No AI preview is silently imported or published', () => {
    const service = read('app/Services/Creative/DownloadPrivateCreativePreview.php');
    const output = read('app/Http/Controllers/Api/V1/DownloadPrivateCreativePreviewController.php');
    const panel = read('resources/js/Pages/Organisations/Brands/Content/PrivateCreativePreviewPanel.jsx');
    assert.match(service, /CREATIVE_IMAGE_PREVIEW/);
    assert.match(service, /markUncertain/);
    assert.match(output, /no-store/);
    assert.match(output, /X-Canonical-Media/);
    assert.match(panel, /URL\.createObjectURL/);
    assert.match(panel, /URL\.revokeObjectURL/);
    assert.match(panel, /upload it through canonical Storage/i);
    assert.doesNotMatch(service, /importStorageMedia|linkMedia|publication\.arm|->publish\(/);
    assert.doesNotMatch(panel, /localStorage|sessionStorage/);
});

test('Human resource correction uses old-hash compare-and-swap under tenant lock', () => {
    const repo = read('app/Repository/Eloquent/PlannerAgentAutonomyRepository.php');
    const manager = read('app/Services/Agents/ManagePlannerAgentDelegations.php');
    const routes = read('routes/web.php');
    assert.match(repo, /resource\.revised/);
    assert.match(repo, /previous_sha256/);
    assert.match(repo, /resource_revision_count/);
    assert.match(repo, /hash_equals\(\(string\)\$r->resource_hash,\$expectedHash\)/);
    assert.match(manager, /public function reviseResource/);
    assert.match(manager, /ownedGrant\(/);
    assert.match(routes, /RevisePlannerAutonomousResourceController/);
    assert.match(routes, /kinship\.permission:content_item\.store/);
    assert.doesNotMatch(read('app/Domain/Agent/AgentDelegationPolicy.php'), /'resource\.revise'/);
});

test('First party review UI exposes explicit saving and prevents promotion of unsaved edits', () => {
    const ui = read('resources/js/Pages/Organisations/Brands/Content/AgentDelegationPanel.jsx');
    const index = read('resources/js/Pages/Organisations/Brands/Content/Index.jsx');
    assert.match(ui, /HumanResourceEditor/);
    assert.match(ui, /expected_resource_hash/);
    assert.match(ui, /Save reviewed corrections/);
    assert.match(ui, /disabled=\{busy \|\| changed\}/);
    assert.match(index, /PrivateCreativePreviewPanel/);
    assert.doesNotMatch(ui, /localStorage|sessionStorage/);
});

test('Uncertain AI calls consume daily quota and are not falsely marked successful', () => {
    const repo = read('app/Repository/Eloquent/BrandAiUsageRepository.php');
    const worker = read('app/Services/Agents/ProcessAutonomousAgentRuns.php');
    assert.match(repo, /'RESERVED','SUCCEEDED','UNCERTAIN'/);
    assert.match(repo, /markUncertain/);
    assert.match(repo, /'status'=>'UNCERTAIN'/);
    assert.match(repo, /remaining.*\$u/);
    assert.match(worker, /markUncertain\(\$reservation\)/);
});
