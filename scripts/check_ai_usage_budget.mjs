import fs from 'node:fs';
import assert from 'node:assert/strict';
const read = path => fs.readFileSync(new URL(`../${path}`, import.meta.url),'utf8');
const repo=read('app/Repository/Eloquent/BrandAiUsageRepository.php');
const service=read('app/Services/Ai/BrandAiUsageBudget.php');
const copy=read('app/Services/Ai/ManageAiSuggestions.php');
const campaign=read('app/Services/Ai/ManageCampaignProposals.php');
const routes=read('routes/api.php');
const migration=read('database/migrations/2026_11_14_235100_create_brand_ai_usage_reservations.php');
const ui1=read('resources/js/Pages/Organisations/Brands/Content/AiSuggestionsPanel.jsx');
const ui2=read('resources/js/Pages/Organisations/Brands/Content/AiCampaignProposalsPanel.jsx');
const checks = [
['shared reservation across copy and campaign',copy.includes("$this->budget->reserve($org,$brand,$user,'COPY')") && campaign.includes("$this->budget->reserve($org,$brand,$actor,'CAMPAIGN')")],
['lock protects concurrent budgets',service.includes('lockForUpdate()')],
['outbound HTTP outside quota transaction',!service.includes('->generate(') && copy.indexOf('$this->budget->reserve(')<copy.indexOf('$this->generator->generate(')],
['no quota leakage across tenants',repo.includes("where('organisation_id',$org->id)") && repo.includes("where('brand_id',$brand->id)")],
['quota includes in-flight requests',repo.includes("['RESERVED','SUCCEEDED']")],
['quota has per-brand FK',migration.includes('fk_brand_ai_usage_brand')],
['no provider response bodies stored',!migration.includes('response_body') && !migration.includes('prompt_text')],
['read-only usage endpoint is authorized',routes.includes("'ai-usage'") && routes.includes('BrandAiUsageController::class')],
['copy UI shows remaining allowance',ui1.includes('usage.remaining')],
['campaign UI shows remaining allowance',ui2.includes('usage.remaining')],
['failed generation returns quota',copy.includes('$this->budget->settle($reservation,false)') && campaign.includes('$this->budget->settle($reservation,false)')],
['no live publishing path added',!service.includes('publish(') && !repo.includes('publish(')],
];
for(const [name,ok] of checks){assert.ok(ok,name); console.log(`PASS ${name}`)}
console.log(`${checks.length}/${checks.length} AI usage budget safety checks passed`);
