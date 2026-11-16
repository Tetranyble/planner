import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read = f => readFileSync(new URL(`../../${f}`,import.meta.url),'utf8');
test('canonical Storage candidates are scoped to organisation and the uploading actor',()=>{
    const s=read('app/Repository/Eloquent/StorageMediaCandidateRepository.php');
    assert.match(s,/workspace_id/);assert.match(s,/uploaded_by/);
    assert.match(s,/whereNull\('quarantined_at'\)/);
});
test('media browser is authorized twice and validates canonical safety',()=>{
    const code=read('app/Services/Content/ListStorageMediaCandidates.php');
    assert.match(code,/media_asset.index/);assert.match(code,/inspect\(\$org,\$actor/);
    assert.match(code,/catch \(ValidationException\)/);
});
test('React selects a known Storage media id without making a new uploader',()=>{
    const code=read('resources/js/Pages/Organisations/Brands/Content/Index.jsx');
    assert.match(code,/Browse my verified uploads/);assert.match(code,/storage-candidates/);
    assert.match(code,/storage_media_id/);
});
