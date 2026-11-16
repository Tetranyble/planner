import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = (file) => readFileSync(new URL(`../../${file}`, import.meta.url), 'utf8');

test('independent review stays behind Kinship owner permission in both transports', () => {
    for (const file of ['routes/web.php','routes/api.php']) {
        const routes=read(file);
        assert.match(routes, /kinship\.role:owner/);
        assert.match(routes, /kinship\.permission:social-grant\.reconcile/);
        assert.match(routes, /grant-revocations\/\{requestUuid\}\/reconciliation-evidence/);
        assert.match(routes, /grant-revocations\/\{requestUuid\}\/reconcile/);
    }
});
test('request service enforces a second owner and does not claim provider API acknowledgement', () => {
    const repo=read('app/Repository/Eloquent/ProviderGrantReconciliationRepository.php');
    assert.match(repo, /submitted_by_user_id === \$actorId/);
    assert.match(repo, /MANUAL_ATTESTED_REVOKED/);
    assert.match(repo, /MANUAL_CONFIRMED_ACTIVE/);
    assert.doesNotMatch(repo, /provider_acknowledged_at.*=>/);
    assert.match(repo, /ProviderConnectionCredential::query\(\)/);
});
test('new controllers follow CRUD or one invoke action', () => {
    for (const segment of ['Api/V1','Workspace']) {
        const controller=read(`app/Http/Controllers/${segment}/GrantReconciliationController.php`);
        assert.match(controller,/function index\(/);
        assert.match(controller,/function store\(/);
        const decision=read(`app/Http/Controllers/${segment}/DecideGrantReconciliationController.php`);
        assert.match(decision,/function __invoke\(/);
        assert.doesNotMatch(decision,/function (?:store|index|decide|resolve)\(/);
    }
});
test('desk labels manual attestation distinctly from provider confirmation', () => {
    const ui=read('resources/js/Pages/Organisations/Social/GrantReconciliations.jsx');
    assert.match(ui,/manual decision/);
    assert.match(ui,/different organisation owner/);
    assert.match(ui,/never represented as API acknowledgements/);
});
