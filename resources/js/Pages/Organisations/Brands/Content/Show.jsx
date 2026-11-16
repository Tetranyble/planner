import PublicationIntentsPanel from './PublicationIntentsPanel';
import RiskDeclarations from './RiskDeclarations';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Head, Link, router, useForm } from '@inertiajs/react';

const field='mt-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-2 focus-visible:outline-ring';
const panel='rounded-xl border border-border bg-card p-5 shadow-card';
function ApprovalSubmit({base, itemUuid, target}) {
    const form = useForm({expected_target_hash: target.payload_hash});
    return <form onSubmit={e => {e.preventDefault();form.post(`${base}/${itemUuid}/targets/${target.uuid}/approvals`,{preserveScroll:true});}} className="mt-2">
        <Button size="sm" type="submit" variant="outline" disabled={form.processing}>Request approval of this exact draft</Button>
        {Object.values(form.errors).map((message,i)=><p className="text-xs text-destructive" role="alert" key={i}>{message}</p>)}
    </form>;
}
function ApprovalReview({base,itemUuid,request}) {
    const form=useForm({decision:'APPROVE',comment:''});
    return <form className="mt-3 space-y-2" onSubmit={e=>{e.preventDefault();form.post(`${base}/${itemUuid}/approvals/${request.id}/decisions`,{preserveScroll:true});}}>
        <select aria-label="Review decision" className={field} value={form.data.decision} onChange={e=>form.setData('decision',e.target.value)}>
            <option value="APPROVE">Approve</option><option value="REJECT">Reject</option>
        </select>
        <textarea aria-label="Review rationale" required minLength={8} maxLength={2000} className={field} rows={2} value={form.data.comment}
            onChange={e=>form.setData('comment',e.target.value)} placeholder="Explain your review decision" />
        {Object.values(form.errors).map((message,i)=><p className="text-xs text-destructive" role="alert" key={i}>{message}</p>)}
        <Button size="sm" type="submit" disabled={form.processing}>Record independent decision</Button>
    </form>;
}
export default function Show({organisation,brand,studio,can,approvals=[],actorId}) {
    const base=`/organisations/${organisation.uuid}/brands/${brand.uuid}/content`;
    const {item,versions,media,accounts,targets=[]}=studio;
    const latest=versions[0];
    const revise=useForm({expected_version:latest?.version_number ?? 1,copy_text:latest?.copy_text ?? '',media_uuids:(latest?.media ?? []).map(x => x.uuid).filter(Boolean),ai_generated:latest?.ai_generated ?? null,includes_sensitive_claim:latest?.includes_sensitive_claim ?? null});
    const target=useForm({version_uuid:latest?.uuid ?? '',social_account_uuid:accounts?.[0]?.uuid ?? '',platform_caption:latest?.copy_text ?? '',publishing_mode:'ASSISTED'});
    const variants=useForm({version_uuid:latest?.uuid ?? '',variants:(accounts??[]).slice(0,20).map(account=>({social_account_uuid:account.uuid, platform_caption:latest?.copy_text ?? ''}))});
    const captionLimits={facebook:5000,instagram:2200,linkedin:3000,x:280,tiktok:2200,youtube:5000};
    const updateVariant=(uuid,copy)=>variants.setData('variants',variants.data.variants.map(v=>v.social_account_uuid===uuid?{...v,platform_caption:copy}:v));

    return <AuthenticatedLayout header={<div className="flex items-center justify-between gap-3"><h1 className="text-xl font-semibold">{item.title}</h1><Link className="text-sm text-primary hover:underline" href={base}>All drafts</Link></div>}>
        <Head title={`${item.title} · Planner`} />
        <div className="mx-auto grid max-w-7xl gap-6 px-4 py-7 lg:grid-cols-2 sm:px-6">
            <section className={panel}><h2 className="text-lg font-semibold">Immutable version history</h2><p className="mt-1 text-sm text-muted-foreground">A new version never modifies an older version or its approval references.</p>
                <ol className="mt-4 space-y-4">{versions.map(v=><li className="rounded-lg border border-border p-3" key={v.uuid}><h3 className="font-medium">Version {v.version_number}</h3><p className="mt-2 whitespace-pre-wrap text-sm">{v.copy_text}</p>{v.media?.length>0 && <p className="mt-2 text-xs text-muted-foreground">Media: {v.media.map(asset=>asset.name).join(', ')}</p>}<p className="mt-2 text-xs text-muted-foreground">AI-generated: {v.ai_generated===null?'Unknown':v.ai_generated?'Yes':'No'} · Sensitive claims: {v.includes_sensitive_claim===null?'Unknown':v.includes_sensitive_claim?'Yes':'No'}</p><p className="mt-2 break-all font-mono text-xs text-muted-foreground">SHA-256: {v.content_hash}</p></li>)}</ol>
            </section>
            <div className="space-y-6">
                {can.revise && <section className={panel}><h2 className="text-lg font-semibold">Create next version</h2><form className="mt-4 space-y-3" onSubmit={e=>{e.preventDefault();revise.post(`${base}/${item.uuid}/versions`,{preserveScroll:true});}}>
                    <p className="text-sm text-muted-foreground">Based on version {latest.version_number}; stale edits will be rejected.</p>
                    <label className="block text-sm">Revised text<textarea className={field} rows={6} required maxLength={50000} value={revise.data.copy_text} onChange={e=>revise.setData('copy_text',e.target.value)}/></label>
                    <RiskDeclarations form={revise} />
                    {media.length>0 && <fieldset className="space-y-1"><legend className="text-sm">Include media</legend>{media.map(m=><label key={m.uuid} className="flex items-center gap-2 text-sm"><input type="checkbox" checked={revise.data.media_uuids.includes(m.uuid)} onChange={e=>revise.setData('media_uuids', e.target.checked?[...revise.data.media_uuids,m.uuid]:revise.data.media_uuids.filter(v=>v!==m.uuid))}/>{m.file_name}</label>)}</fieldset>}
                    {Object.keys(revise.errors).length>0 && <p role="alert" className="text-xs text-destructive">{Object.values(revise.errors).join(' · ')}</p>}
                    <Button disabled={revise.processing} type="submit">Save new version</Button></form></section>}
                {can.target && <section className={panel}><h2 className="text-lg font-semibold">Draft platform variation</h2><p className="mt-1 text-sm text-muted-foreground">Creates an account-specific draft only. Publishing remains disabled until approvals and provider policies are implemented.</p>
                    {targets.length>0&&<ul className="mt-3 space-y-2">{targets.map(entry=><li key={entry.uuid} className="rounded-md border border-border p-2 text-sm">
                        <p>{entry.platform_caption}</p>
                        <span className="text-xs text-muted-foreground">{entry.publishing_mode} · Not scheduled</span>
                        {can.requestApproval && entry.version_uuid===latest?.uuid && !approvals.some(a=>a.target_uuid===entry.uuid && a.status==='PENDING') &&
                            <ApprovalSubmit base={base} itemUuid={item.uuid} target={entry}/>}
                    </li>)}</ul>}
                    {accounts.length>1 && <form className="mt-4 space-y-3 border-t border-border pt-4" onSubmit={e=>{e.preventDefault();variants.post(`${base}/${item.uuid}/targets/bulk`,{preserveScroll:true});}}>
                        <h3 className="font-medium">Multi-platform variants (atomic)</h3>
                        <p className="text-xs text-muted-foreground">Create up to 20 account-specific drafts together. These editorial limits do not guarantee platform acceptance. All drafts still require approval.</p>
                        <label className="block text-sm">Content version<select aria-label="Bulk target version" className={field} value={variants.data.version_uuid} onChange={e=>variants.setData('version_uuid',e.target.value)}>{versions.map(v=><option key={v.uuid} value={v.uuid}>Version {v.version_number}</option>)}</select></label>
                        {variants.data.variants.map((v,i)=>{const account=accounts.find(a=>a.uuid===v.social_account_uuid);const limit=captionLimits[String(account?.provider??'').toLowerCase()]??5000;const length=Array.from(v.platform_caption).length;return <label className="block text-sm" key={v.social_account_uuid}>{account?.provider} · {account?.display_name}<textarea aria-label={`Caption for ${account?.display_name}`} className={field} rows={3} value={v.platform_caption} maxLength={5000} onChange={e=>updateVariant(v.social_account_uuid,e.target.value)} /><span className={length>limit?'text-destructive text-xs':'text-muted-foreground text-xs'}>{length}/{limit} editorial characters</span></label>;})}
                        {Object.keys(variants.errors).length>0&&<p role="alert" className="text-xs text-destructive">{Object.values(variants.errors).join(' · ')}</p>}
                        <Button type="submit" disabled={variants.processing||variants.data.variants.some(v=>!v.platform_caption.trim()||Array.from(v.platform_caption).length>(captionLimits[String(accounts.find(a=>a.uuid===v.social_account_uuid)?.provider??'').toLowerCase()]??5000))}>Save all platform variants</Button>
                    </form>}
                    {accounts.length ? <form className="mt-4 space-y-3" onSubmit={e=>{e.preventDefault();target.post(`${base}/${item.uuid}/targets`,{preserveScroll:true});}}>
                        <label className="block text-sm">Version<select className={field} value={target.data.version_uuid} onChange={e=>target.setData('version_uuid',e.target.value)}>{versions.map(v=><option key={v.uuid} value={v.uuid}>Version {v.version_number}</option>)}</select></label>
                        <label className="block text-sm">Social account<select className={field} value={target.data.social_account_uuid} onChange={e=>target.setData('social_account_uuid',e.target.value)}>{accounts.map(a=><option key={a.uuid} value={a.uuid}>{a.provider} · {a.display_name}</option>)}</select></label>
                        <label className="block text-sm">Publishing mode<select className={field} value={target.data.publishing_mode} onChange={e=>target.setData('publishing_mode',e.target.value)}><option value="ASSISTED">Assisted (no automatic delivery)</option><option value="AUTOMATIC">Automatic — verified production text accounts only</option></select></label>
                        <p className="text-xs text-muted-foreground">Automatic targets require a new independent content approval and production provider authorization. They never publish immediately.</p>
                        <label className="block text-sm">Platform caption<textarea className={field} rows={4} value={target.data.platform_caption} onChange={e=>target.setData('platform_caption',e.target.value)} required maxLength={5000}/></label>
                        {Object.keys(target.errors).length>0&&<p className="text-xs text-destructive" role="alert">{Object.values(target.errors).join(' · ')}</p>}
                        <Button type="submit" disabled={target.processing}>Save platform draft</Button>
                    </form> : <p className="mt-4 text-sm text-muted-foreground">Connect a social account to draft a variation.</p>}
                </section>}
                <section className={panel}>
                    <h2 className="text-lg font-semibold">Content approvals</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Each request pins the immutable content version, account target, media digests and approval policy. Approval is not a publishing job.</p>
                    {approvals.length ? <ol className="mt-3 space-y-3">{approvals.map(a=><li key={a.id} className="rounded-lg border border-border p-3 text-sm">
                        <div className="flex flex-wrap items-center justify-between gap-2"><strong>Review #{a.id} · {a.status}</strong><span className="text-xs text-muted-foreground">Version {a.version_number}</span></div>
                        <p className="mt-1 text-xs text-muted-foreground">{a.approvals_received}/{a.required_approvals} required independent decisions</p>
                        <p className="mt-1 break-all font-mono text-xs text-muted-foreground">{a.payload_hash}</p>
                        {a.decisions?.map((decision,i)=><p className="mt-2 text-xs" key={i}>{decision.decision}: {decision.comment}</p>)}
                        {a.status==='PENDING' && can.withdrawApproval && a.requested_by_user_id===actorId && <Button variant="outline" size="sm" className="mt-3" onClick={()=>router.post(`${base}/${item.uuid}/approvals/${a.id}/withdraw`,{},{preserveScroll:true})}>Withdraw request</Button>}
                        {a.status==='PENDING' && can.reviewApproval && a.requested_by_user_id!==actorId && !a.decisions?.some(d=>d.reviewer_user_id===actorId) && <ApprovalReview base={base} itemUuid={item.uuid} request={a}/>}
                    </li>)}</ol>: <p className="mt-4 text-sm text-muted-foreground">No approvals submitted yet.</p>}
                </section>
                {can.requestApproval && <PublicationIntentsPanel organisation={organisation} brand={brand} item={item} approvals={approvals} />}
            </div>
        </div>
    </AuthenticatedLayout>;
}
