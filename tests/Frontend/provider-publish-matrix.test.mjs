import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const src = f => readFileSync(new URL(`../../${f}`, import.meta.url),'utf8');
test('publish readiness maps six specific provider capabilities and connected enum',()=>{
 const matrix=src('app/Domain/Content/ProviderPublishCapabilityMatrix.php');
 for(const provider of ['facebook','instagram','linkedin','x','tiktok','youtube']) assert.ok(matrix.includes(`'${provider}'`));
 const repository=src('app/Repository/Eloquent/PublicationReadinessRepository.php');
 assert.ok(repository.includes('ProviderPublishCapabilityMatrix::capabilityFor'));
 assert.ok(repository.includes('ProviderConnectionStatus::CONNECTED->value'));
 assert.ok(repository.includes("->where('mode', 'AUTOMATIC')"));
 assert.ok(repository.includes("->where('valid_until', '>', now())"));
});
