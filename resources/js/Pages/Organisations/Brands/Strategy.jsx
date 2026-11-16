import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass = 'mt-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const panelClass = 'rounded-xl border border-border bg-card p-5 text-card-foreground shadow-card';
const lines = (text) => text.split('\n').map((v) => v.trim()).filter(Boolean);
function Field({ label, error, children }) { return <label className="block text-sm font-medium text-foreground">{label}{children}{error && <span className="mt-1 block text-xs text-destructive" role="alert">{error}</span>}</label>; }
function Text({ value, onChange, rows = 2, maxLength }) { return <textarea className={inputClass} value={value} onChange={e => onChange(e.target.value)} rows={rows} maxLength={maxLength} />; }
function TextInput({ value, onChange, type = 'text', min, max }) { return <input className={inputClass} type={type} min={min} max={max} value={value} onChange={e => onChange(e.target.value)} />; }
function ReadOnly({ title, children }) { return <section className={panelClass}><h2 className="font-semibold">{title}</h2><div className="mt-3 space-y-2 text-sm text-muted-foreground">{children}</div></section>; }
function Submit({ processing, children }) { return <Button type="submit" disabled={processing}>{processing ? 'Saving…' : children}</Button>; }
function Section({ title, description, children }) { return <section className={panelClass}><h2 className="text-lg font-semibold">{title}</h2><p className="mb-4 mt-1 text-sm text-muted-foreground">{description}</p>{children}</section>; }
function Flag({ checked, onChange, children }) { return <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={checked} onChange={e=>onChange(e.target.checked)} className="accent-primary" />{children}</label>; }
function Profile({ initial, path }) {
    const form = useForm({ positioning: initial?.positioning ?? '', default_locale: initial?.default_locale ?? 'en-NG' });
    return <Section title="Brand positioning" description="Approved messaging foundation; not itself a publishable claim.">
        <form onSubmit={e=>{e.preventDefault();form.patch(`${path}/profile`, {preserveScroll:true});}} className="space-y-3">
            <Field label="Positioning statement" error={form.errors.positioning}><Text value={form.data.positioning} onChange={v=>form.setData('positioning', v)} maxLength={255} /></Field>
            <Field label="Default locale" error={form.errors.default_locale}><TextInput value={form.data.default_locale} onChange={v=>form.setData('default_locale', v)} /></Field>
            <Submit processing={form.processing}>Save positioning</Submit>
        </form>
    </Section>;
}
function Audience({ entries, path }) {
    const f = useForm({name:'',description:'',audience_kind:'BUSINESS',priority:50,rolesText:'',painsText:'',goalsText:''});
    const submit=e=>{e.preventDefault();f.transform(v=>({name:v.name,description:v.description,audience_kind:v.audience_kind,priority:Number(v.priority),
        roles:lines(v.rolesText).map(role=>({role,decision_role:'DECISION_MAKER'})),
        pain_points:lines(v.painsText).map(description=>({description,priority:50})),
        goals:lines(v.goalsText).map(description=>({description,priority:50}))
    })).post(`${path}/audiences`,{preserveScroll:true,onSuccess:()=>f.reset()});};
    return <Section title="Audience segments" description="Specify who the marketing is intended for, their decision roles, obstacles and goals.">
        {entries.length>0&&<ul className="mb-4 space-y-2 text-sm">{entries.map(x=><li key={x.uuid} className="rounded-md bg-muted p-3"><strong>{x.name}</strong> — {x.audience_kind}<span className="ml-2 text-muted-foreground">({x.roles.length} roles, {x.pain_points.length} pains, {x.goals.length} goals)</span></li>)}</ul>}
        <form onSubmit={submit} className="space-y-3">
            <Field label="Audience name" error={f.errors.name}><TextInput value={f.data.name} onChange={v=>f.setData('name',v)} /></Field>
            <Field label="Description" error={f.errors.description}><Text value={f.data.description} onChange={v=>f.setData('description',v)} /></Field>
            <Field label="Audience category"><select className={inputClass} value={f.data.audience_kind} onChange={e=>f.setData('audience_kind',e.target.value)}>{['BUSINESS','CONSUMER','PARTNER','INTERNAL'].map(k=><option key={k}>{k}</option>)}</select></Field>
            <Field label="Decision-maker roles (one per line)" error={f.errors.roles}><Text rows={3} value={f.data.rolesText} onChange={v=>f.setData('rolesText',v)} /></Field>
            <Field label="Problems to solve (one per line)" error={f.errors.pain_points}><Text rows={3} value={f.data.painsText} onChange={v=>f.setData('painsText',v)} /></Field>
            <Field label="Audience goals (one per line)" error={f.errors.goals}><Text rows={3} value={f.data.goalsText} onChange={v=>f.setData('goalsText',v)} /></Field>
            <Submit processing={f.processing}>Add audience segment</Submit>
        </form>
    </Section>;
}
function Voice({ entries, path }) {
    const f=useForm({name:'',locale:'en-NG',tone_description:'',is_default:true,formality:3,warmth:3,directness:3,rulesText:'',avoidTermsText:'',preferTermsText:''});
    const submit=e=>{e.preventDefault();f.transform(v=>({name:v.name,locale:v.locale,tone_description:v.tone_description,is_default:v.is_default,formality:Number(v.formality),warmth:Number(v.warmth),directness:Number(v.directness),
        rules:lines(v.rulesText).map(instruction=>({rule_kind:'DO',enforcement:'GUIDELINE',instruction,priority:50})),
        terms:[...lines(v.avoidTermsText).map(term=>({term,usage:'AVOID'})),...lines(v.preferTermsText).map(term=>({term,usage:'PREFER'}))]
    })).post(`${path}/voices`,{preserveScroll:true,onSuccess:()=>f.reset()});};
    return <Section title="Brand voice" description="Tone, enforceable writing guidelines and preferred or prohibited vocabulary.">
        {entries.length>0&&<ul className="mb-4 space-y-2 text-sm">{entries.map(v=><li key={v.uuid} className="rounded-md bg-muted p-3"><strong>{v.name}</strong> · {v.locale}{v.is_default?' · Default':''} · {v.rules.length} rules</li>)}</ul>}
        <form onSubmit={submit} className="space-y-3">
            <div className="grid gap-3 sm:grid-cols-2"><Field label="Profile name" error={f.errors.name}><TextInput value={f.data.name} onChange={v=>f.setData('name',v)}/></Field><Field label="Locale" error={f.errors.locale}><TextInput value={f.data.locale} onChange={v=>f.setData('locale',v)}/></Field></div>
            <Field label="Tone description" error={f.errors.tone_description}><Text value={f.data.tone_description} onChange={v=>f.setData('tone_description',v)} rows={3}/></Field>
            <div className="grid grid-cols-3 gap-2">{['formality','warmth','directness'].map(k=><Field key={k} label={`${k} (1–5)`}><TextInput type="number" min="1" max="5" value={f.data[k]} onChange={v=>f.setData(k,v)}/></Field>)}</div>
            <Flag checked={f.data.is_default} onChange={v=>f.setData('is_default',v)}>Default voice for this locale</Flag>
            <Field label="Writing rules (one per line)" error={f.errors.rules}><Text rows={3} value={f.data.rulesText} onChange={v=>f.setData('rulesText',v)}/></Field>
            <div className="grid gap-3 sm:grid-cols-2"><Field label="Avoid these terms" error={f.errors.terms}><Text rows={3} value={f.data.avoidTermsText} onChange={v=>f.setData('avoidTermsText',v)}/></Field><Field label="Prefer these terms"><Text rows={3} value={f.data.preferTermsText} onChange={v=>f.setData('preferTermsText',v)}/></Field></div>
            <Submit processing={f.processing}>Create voice profile</Submit>
        </form>
    </Section>;
}
const policyKeys=[['approved_claims_only','Require approved claims'],['evidence_required_for_quantified','Evidence for quantified claims'],['evidence_required_for_comparisons','Evidence for comparisons'],['allow_guaranteed_outcome_claims','Permit guaranteed-outcome claims'],['require_review_for_new_claims','Review every new claim']];
function ClaimPolicy({initial,path}) {
    const f=useForm(initial);
    return <Section title="Claims governance" description="Marketing claims are not automatically publishable, even if a policy setting is relaxed.">
        <form onSubmit={e=>{e.preventDefault();f.patch(`${path}/claims/policy`,{preserveScroll:true});}} className="space-y-3">
            {policyKeys.map(([k,v])=><Flag key={k} checked={Boolean(f.data[k])} onChange={x=>f.setData(k,x)}>{v}</Flag>)}
            <Submit processing={f.processing}>Save claims policy</Submit>
        </form>
    </Section>;
}
function Claims({entries,path,canSubmit=false,canReview=false}) {
    const f=useForm({claim_text:'',claim_category:'PRODUCT_CAPABILITY'});
    return <Section title="Claims register" description="New claims are recorded as DRAFT, never automatically approved or publishable.">
        {entries.length>0&&<ul className="mb-4 max-h-52 space-y-2 overflow-auto text-sm">{entries.map(x=><li key={x.uuid} className="rounded-md border border-border p-2"><span className="font-medium">{x.status}</span> — {x.claim_text}
            {canSubmit&&x.status==='DRAFT'&&<SubmitClaim item={x} path={path}/>}
            {canReview&&x.status==='IN_REVIEW'&&<DecisionForm endpoint={`${path}/claims/${x.uuid}/review`} choices={['APPROVE','REJECT']} label="Review marketing claim"/>}</li>)}</ul>}
        <form onSubmit={e=>{e.preventDefault();f.post(`${path}/claims`,{preserveScroll:true,onSuccess:()=>f.reset('claim_text')});}} className="space-y-3">
            <Field label="Proposed claim" error={f.errors.claim_text}><Text value={f.data.claim_text} onChange={v=>f.setData('claim_text',v)} rows={3}/></Field>
            <Field label="Category" error={f.errors.claim_category}><select className={inputClass} value={f.data.claim_category} onChange={e=>f.setData('claim_category',e.target.value)}>{['PRODUCT_CAPABILITY','QUANTIFIED','COMPARATIVE','OUTCOME','PRICING','OTHER'].map(k=><option key={k}>{k}</option>)}</select></Field>
            <Submit processing={f.processing}>Save draft claim</Submit>
        </form>
    </Section>;
}
function ApprovalPolicies({entries,path,canActivate=false}) {
    const f=useForm({description:'',default_required_approvals:1,ruleNames:''});
    const submit=e=>{e.preventDefault();f.transform(v=>({description:v.description,default_required_approvals:Number(v.default_required_approvals),
        rules:lines(v.ruleNames).map((rule_name,i)=>({rule_name,priority:i+1,action_kind:'PUBLISH',required_approvals:Number(v.default_required_approvals)}))
    })).post(`${path}/approval-policies`,{preserveScroll:true,onSuccess:()=>f.reset()});};
    return <Section title="Publishing approval policy" description="Draft only. Live activation requires the separately governed approval-policy lifecycle.">
        {entries.length>0&&<ul className="mb-4 space-y-2 text-sm">{entries.map(x=><li key={x.uuid} className="rounded-md bg-muted p-3">Version {x.policy_version} — {x.state} · {x.default_required_approvals} approval(s)
            {canActivate&&x.state==='DRAFT'&&<ActivatePolicy item={x} path={path}/>}</li>)}</ul>}
        <form onSubmit={submit} className="space-y-3">
            <Field label="Policy description" error={f.errors.description}><Text value={f.data.description} onChange={v=>f.setData('description',v)}/></Field>
            <Field label="Approvals required (1–10)" error={f.errors.default_required_approvals}><TextInput type="number" min="1" max="10" value={f.data.default_required_approvals} onChange={v=>f.setData('default_required_approvals',v)}/></Field>
            <Field label="Publishing approval rules (one name per line)" error={f.errors.rules}><Text value={f.data.ruleNames} onChange={v=>f.setData('ruleNames',v)} rows={3}/></Field>
            <Submit processing={f.processing}>Create draft policy</Submit>
        </form>
    </Section>;
}
function DecisionForm({endpoint, choices, label}) {
    const f=useForm({decision: choices[0],rationale:''});
    const submit=e=>{e.preventDefault();f.post(endpoint,{preserveScroll:true,onSuccess:()=>f.reset('rationale')});};
    return <form onSubmit={submit} className="mt-2 flex flex-wrap items-end gap-2" aria-label={label}>
        <label className="text-xs text-muted-foreground">Decision
            <select className={inputClass} value={f.data.decision} onChange={e=>f.setData('decision',e.target.value)}>
                {choices.map(value=><option key={value}>{value}</option>)}
            </select>
        </label>
        <label className="min-w-48 flex-1 text-xs text-muted-foreground">Review rationale
            <input required minLength={10} maxLength={2000} className={inputClass} value={f.data.rationale} onChange={e=>f.setData('rationale',e.target.value)} />
        </label>
        <Submit processing={f.processing}>Record decision</Submit>
        {f.hasErrors&&<p role="alert" className="w-full text-xs text-destructive">{Object.values(f.errors).join(' · ')}</p>}
    </form>;
}
function Sources({entries,path,canCreate,canReview}) {
    const f=useForm({title:'',source_type:'MANUAL',source_url:'',content_text:''});
    return <Section title="Knowledge sources" description="Sources remain PENDING until independently verified. A URL is evidence metadata, not permission to scrape or ingest a website.">
        <div className="space-y-3">{entries.map(source=><div className="rounded-md border border-border p-3 text-sm" key={source.uuid}>
            <strong>{source.title}</strong> <span className="text-muted-foreground">· {source.trust_status}</span>
            {source.source_url&&<p className="break-all text-xs">{source.source_url}</p>}
            {canReview&&source.trust_status==='PENDING'&&<DecisionForm endpoint={`${path}/knowledge-sources/${source.uuid}/review`} choices={['VERIFY','REJECT']} label={`Review ${source.title}`}/>}
        </div>)}</div>
        {!entries.length&&<p className="mb-3 text-sm text-muted-foreground">No knowledge sources registered.</p>}
        {canCreate&&<form className="mt-4 space-y-3" onSubmit={e=>{e.preventDefault();f.post(`${path}/knowledge-sources`,{preserveScroll:true,onSuccess:()=>f.reset()});}}>
            <Field label="Source title" error={f.errors.title}><TextInput value={f.data.title} onChange={v=>f.setData('title',v)}/></Field>
            <Field label="Source type"><select className={inputClass} value={f.data.source_type} onChange={e=>f.setData('source_type',e.target.value)}><option value="MANUAL">Internal reference</option><option value="WEB">External website</option></select></Field>
            {f.data.source_type==='WEB'?<Field label="Source URL" error={f.errors.source_url}><TextInput value={f.data.source_url} onChange={v=>f.setData('source_url',v)} /></Field>:<Field label="Source text" error={f.errors.content_text}><Text value={f.data.content_text} onChange={v=>f.setData('content_text',v)} rows={4}/></Field>}
            <Submit processing={f.processing}>Submit source for verification</Submit>
        </form>}
    </Section>;
}
function Evidence({entries,claims,sources,path,canCreate,canReview}) {
    const f=useForm({claim_uuid:'',source_uuid:'',supporting_excerpt:'',source_locator:''});
    return <Section title="Claim evidence" description="Each evidence item must reference an existing claim and source within this brand; submissions are not verified by default.">
        <ul className="space-y-3">{entries.map(item=><li key={item.uuid} className="rounded-md border border-border p-3 text-sm">
            <p>{item.supporting_excerpt}</p><span className="text-xs text-muted-foreground">Review state: {item.review_state}</span>
            {canReview&&item.review_state==='PENDING'&&<DecisionForm endpoint={`${path}/evidence/${item.uuid}/review`} choices={['VERIFY','REJECT']} label="Review claim evidence"/>}
        </li>)}</ul>
        {canCreate&&<form className="mt-4 space-y-3" onSubmit={e=>{e.preventDefault();const claim=f.data.claim_uuid;f.transform(v=>({source_uuid:v.source_uuid,supporting_excerpt:v.supporting_excerpt,source_locator:v.source_locator})).post(`${path}/claims/${claim}/evidence`,{preserveScroll:true,onSuccess:()=>f.reset()});}}>
            <Field label="Claim" error={f.errors.claim_uuid}><select required className={inputClass} value={f.data.claim_uuid} onChange={e=>f.setData('claim_uuid',e.target.value)}><option value="">Choose a claim</option>{claims.filter(c=>['DRAFT','IN_REVIEW'].includes(c.status)).map(c=><option key={c.uuid} value={c.uuid}>{c.claim_text.slice(0,110)}</option>)}</select></Field>
            <Field label="Knowledge source" error={f.errors.source_uuid}><select required className={inputClass} value={f.data.source_uuid} onChange={e=>f.setData('source_uuid',e.target.value)}><option value="">Choose a source</option>{sources.map(x=><option key={x.uuid} value={x.uuid}>{x.title} · {x.trust_status}</option>)}</select></Field>
            <Field label="Supporting excerpt" error={f.errors.supporting_excerpt}><Text value={f.data.supporting_excerpt} onChange={v=>f.setData('supporting_excerpt',v)} rows={3}/></Field>
            <Field label="Locator / page number" error={f.errors.source_locator}><TextInput value={f.data.source_locator} onChange={v=>f.setData('source_locator',v)}/></Field>
            <Submit processing={f.processing}>Attach pending evidence</Submit>
        </form>}
    </Section>;
}
function SubmitClaim({item,path}) {
    const form=useForm({});
    return <form className="mt-2" onSubmit={e=>{e.preventDefault();form.post(`${path}/claims/${item.uuid}/submit-review`,{preserveScroll:true});}}><Submit processing={form.processing}>Submit for independent review</Submit></form>;
}
function ActivatePolicy({item,path}) {
    const f=useForm({});
    return <form className="mt-2" onSubmit={e=>{e.preventDefault();f.post(`${path}/approval-policies/${item.uuid}/activate`,{preserveScroll:true});}}><Submit processing={f.processing}>Activate reviewed policy</Submit></form>;
}
export default function BrandStrategy({organisation, brand, strategy, can = {}}) {
    const path = `/organisations/${organisation.uuid}/brands/${brand.uuid}/strategy`;
    return <AuthenticatedLayout header={<h1 className="text-xl font-semibold">{brand.name} — Brand strategy</h1>}>
        <Head title={`${brand.name} strategy`} />
        <main className="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6" aria-label="Brand strategy workspace">
            <Link href={route('organisations.brands.index',{organisation:organisation.uuid})} className="text-sm underline">← {organisation.name} brands</Link>
            <p className="text-sm text-muted-foreground">Brand-specific marketing knowledge. All edits are audit logged and permission-checked.</p>
            <div className="grid gap-5 lg:grid-cols-2">
                {can.profile ? <Profile initial={strategy.profile} path={path}/> : <ReadOnly title="Brand positioning"><p>{strategy.profile?.positioning || "No positioning statement yet."}</p></ReadOnly>}
                {can.claimPolicy ? <ClaimPolicy initial={strategy.claim_policy} path={path}/> : <ReadOnly title="Claims governance"><ul>{policyKeys.map(([key,label])=><li key={key}>{label}: {strategy.claim_policy[key] ? "Yes" : "No"}</li>)}</ul></ReadOnly>}
                {can.audience ? <Audience entries={strategy.audiences} path={path}/> : <ReadOnly title="Audience segments"><ul className="space-y-1">{strategy.audiences.map(x=><li key={x.uuid}>{x.name} · {x.audience_kind}</li>)}{!strategy.audiences.length&&<li>No segments yet.</li>}</ul></ReadOnly>}
                {can.voice ? <Voice entries={strategy.voices} path={path}/> : <ReadOnly title="Brand voice"><ul>{strategy.voices.map(x=><li key={x.uuid}>{x.name}: {x.tone_description || "No description"}</li>)}{!strategy.voices.length&&<li>No voice profiles yet.</li>}</ul></ReadOnly>}
                {can.claim ? <Claims entries={strategy.claims} path={path} canSubmit={can.claimSubmit} canReview={can.claimReview}/> : <ReadOnly title="Claims register"><ul>{strategy.claims.map(x=><li key={x.uuid}>{x.status} — {x.claim_text}</li>)}{!strategy.claims.length&&<li>No claims yet.</li>}</ul></ReadOnly>}
                {can.approvalPolicy ? <ApprovalPolicies entries={strategy.approval_policies} path={path} canActivate={can.policyActivate}/> : <ReadOnly title="Publishing approval policy"><ul>{strategy.approval_policies.map(x=><li key={x.uuid}>Version {x.policy_version}: {x.state}</li>)}{!strategy.approval_policies.length&&<li>No policies yet.</li>}</ul></ReadOnly>}
            </div>
            <div className="grid gap-5 lg:grid-cols-2">
                <Sources entries={strategy.knowledge_sources ?? []} path={path} canCreate={can.sourceCreate} canReview={can.sourceReview}/>
                <Evidence entries={strategy.claim_evidence ?? []} claims={strategy.claims} sources={strategy.knowledge_sources ?? []} path={path} canCreate={can.evidenceCreate} canReview={can.evidenceReview}/>
            </div>
        </main>
    </AuthenticatedLayout>;
}
