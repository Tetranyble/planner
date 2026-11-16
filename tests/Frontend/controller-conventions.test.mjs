import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, relative } from 'node:path';

const controllerRoot = 'app/Http/Controllers';
const permitted = new Set(['index', 'store', 'update', 'destroy', 'show', 'edit', '__invoke']);
const php = (p) => readFileSync(p, 'utf8');
const allPhp = (root) => readdirSync(root).flatMap((name) => {
    const path = join(root, name);
    return statSync(path).isDirectory() ? allPhp(path) : (path.endsWith('.php') ? [path] : []);
});

function controllerMethods(path) {
    return [...php(path).matchAll(/\bpublic\s+function\s+([A-Za-z_][A-Za-z_0-9]*)\s*\(/g)].map((m) => m[1]);
}
function controllerPath(reference, imports) {
    const full = reference.startsWith('\\') ? reference.slice(1) : (imports.get(reference) || reference);
    if (!full.startsWith('App\\Http\\Controllers\\')) return null;
    return `${controllerRoot}/${full.replace(/^App\\Http\\Controllers\\/, '').replaceAll('\\', '/')}.php`;
}
function routeTargets(path) {
    const text = php(path);
    const imports = new Map([...text.matchAll(/^use\s+(App\\Http\\Controllers\\[\w\\]+);/gm)]
        .map((match) => [match[1].split('\\').at(-1), match[1]]));
    const targets = [];
    // Only check real Route calls (not arbitrary text containing a Controller::class).
    const resource = /Route::(?:get|post|patch|put|delete|options|match)\s*\(\s*['"][^'"]+['"]\s*,\s*\[\s*(\\?[\w\\]+)::class\s*,\s*['"](\w+)['"]\s*\]/g;
    for (const m of text.matchAll(resource)) targets.push([controllerPath(m[1], imports), m[2], m[0]]);
    const invokables = /Route::(?:get|post|patch|put|delete|options|match)\s*\(\s*['"][^'"]+['"]\s*,\s*(\\?[\w\\]+)::class\s*\)/g;
    for (const m of text.matchAll(invokables)) targets.push([controllerPath(m[1], imports), '__invoke', m[0]]);
    return targets.filter(([path]) => path !== null);
}

test('all controllers use only six permitted resource actions or one __invoke', () => {
    const files = allPhp(controllerRoot);
    assert.ok(files.length >= 40, 'do not accidentally skip nested controllers');
    for (const path of files) {
        const actions = controllerMethods(path);
        for (const action of actions) {
            assert.ok(permitted.has(action), `${relative('.', path)} has forbidden action ${action}`);
        }
        if (actions.includes('__invoke')) {
            assert.deepEqual(actions, ['__invoke'], `${relative('.', path)} must have one public action`);
        }
    }
});

test('API, web and auth routes target actual controller methods', () => {
    const routes = ['routes/api.php', 'routes/web.php', 'routes/auth.php'];
    let total = 0;
    for (const route of routes) {
        for (const [path, method, definition] of routeTargets(route)) {
            assert.ok(existsSync(path), `missing controller ${path} for ${definition}`);
            assert.ok(controllerMethods(path).includes(method), `missing ${path}::${method} for ${definition}`);
            total++;
        }
    }
    assert.ok(total >= 45, `expected to check all application route actions, only checked ${total}`);
});

test('brand strategy uses one resource controller per lifecycle', () => {
    for (const segment of ['Api/V1', 'Workspace']) {
        for (const [name, method] of Object.entries({
            BrandProfileController: 'update',
            BrandAudienceSegmentController: 'store',
            BrandVoiceProfileController: 'store',
            BrandClaimPolicyController: 'update',
            BrandClaimController: 'store',
            BrandApprovalPolicyController: 'store',
        })) {
            const actions = controllerMethods(`${controllerRoot}/${segment}/${name}.php`);
            assert.deepEqual(actions, [method], `${segment}/${name} should expose only ${method}`);
        }
        assert.deepEqual(controllerMethods(`${controllerRoot}/${segment}/BrandStrategyController.php`), ['show']);
    }
});

test('Kinship route-level authorization is retained after the split', () => {
    for (const route of ['routes/web.php', 'routes/api.php']) {
        const content = php(route);
        for (const permission of [
            'organisation_membership.index', 'organisation_membership.update', 'organisation_membership.delete',
            'organisation_invitation.store', 'organisation_invitation.delete',
            'brand_membership.index', 'brand_membership.store', 'brand_membership.update', 'brand_membership.delete',
            'brand_profile.index', 'brand_profile.update', 'brand_audience_segment.store', 'brand_voice_profile.store',
            'brand_claim_policy.update', 'brand_claim.store', 'brand_approval_policy.store',
        ]) assert.ok(content.includes(`kinship.permission:${permission}`), `${route} missing ${permission}`);
    }
});
