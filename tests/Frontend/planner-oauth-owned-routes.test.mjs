import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = p => readFileSync(p, 'utf8');
test('Passport disables auto-routes; OAuth URLs are explicitly owned by Planner', () => {
    assert.match(read('app/Providers/AppServiceProvider.php'), /Passport::ignoreRoutes\(\)/);
    const routes = read('routes/planner_oauth.php');
    for (const marker of ["'authorize'", "'token'", "'register'", 'oauth-protected-resource', 'oauth-authorization-server']) {
        assert.ok(routes.includes(marker), `Missing explicit route ${marker}`);
    }
    assert.doesNotMatch(read('routes/ai.php'), /Mcp::oauthRoutes\(/);
});
test('OAuth is an independent endpoint and cannot rewrite Sanctum agent scopes', () => {
    const ai = read('routes/ai.php');
    assert.match(ai, /mcp\/planner-agent-oauth/);
    assert.match(ai, /auth:api/);
    assert.match(ai, /planner\.oauth\.agent/);
    assert.match(read('app/Http/Middleware/RequirePlannerOAuthAgentGrant.php'), /scopeForPassportToken/);
    assert.match(read('app/Http/Middleware/RequirePlannerOAuthAgentGrant.php'), /setUserResolver/);
    assert.match(read('app/Models/PlannerOAuthUser.php'), /extends User implements OAuthenticatable/);
});
test('Public client registration, consent and disconnected access are constrained', () => {
    const register = read('app/Services/OAuth/RegisterPlannerOAuthClient.php');
    const consent = read('app/Services/OAuth/ManagePlannerOAuthLinks.php');
    const repo = read('app/Repository/Eloquent/PlannerOAuthLinkRepository.php');
    assert.match(register, /permittedRedirect/);
    assert.match(register, /oauth_dcr_enabled/);
    assert.match(consent, /delegationForConsent/);
    assert.match(repo, /RefreshToken::query\(\)/);
    assert.match(repo, /revoked_at/);
});
