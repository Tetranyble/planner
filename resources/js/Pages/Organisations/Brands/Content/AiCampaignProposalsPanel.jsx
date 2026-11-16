import { useEffect, useState } from 'react';
import { Button } from '@/Components/ui/button';
const field = 'mt-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground';
const providers = ['facebook','instagram','linkedin','x','tiktok','youtube'];
export default function AiCampaignProposalsPanel({ organisationUuid, brandUuid, canCreate, canReview = false }) {
    const url = `/api/v1/organisations/${organisationUuid}/brands/${brandUuid}/content/ai-campaign-proposals`;
    const [items, setItems] = useState([]);
    const [form, setForm] = useState({ title: '', objective: '', audience: '', platforms: ['linkedin'], duration_days: 7, use_provider_evidence: false });
    const [busy, setBusy] = useState(false);
    const [usage, setUsage] = useState(null);
    const usageUrl = `/api/v1/organisations/${organisationUuid}/brands/${brandUuid}/content/ai-usage`;
    const loadUsage = async () => {
        try { const r = await fetch(usageUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const j = await r.json(); if (r.ok) setUsage(j?.data ?? null);
        } catch { /* usage endpoint remains authoritative on POST */ }
    };
    useEffect(() => { loadUsage(); }, [usageUrl]);
    const [message, setMessage] = useState('');
    const [drafts, setDrafts] = useState({});
    const [draftResponses, setDraftResponses] = useState({});
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const load = async () => {
        try { const r = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const j = await r.json(); if (r.ok) setItems(j?.data?.data ?? []);
        } catch { setMessage('Unable to load campaign proposals.'); }
    };
    useEffect(() => { load(); }, [url]);
    const post = async (path, body) => {
        const r = await fetch(path, { method: 'POST', credentials: 'same-origin', headers: {
            Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(),
        }, body: JSON.stringify(body) });
        const j = await r.json(); if (!r.ok) throw new Error(j?.message || Object.values(j?.errors || {}).flat()[0] || 'Request failed');
    };
    const submit = async (e) => {
        e.preventDefault(); setBusy(true); setMessage('');
        try { await post(url, form); setForm({ ...form, title: '', objective: '' });
            setMessage('Campaign proposal saved for independent review; nothing was scheduled.'); await load(); await loadUsage();
        } catch (e) { setMessage(e.message); } finally { await loadUsage(); setBusy(false); }
    };
    const review = async (p, decision) => {
        const note = window.prompt(`Reason for ${decision.toLowerCase()} (at least 5 characters):`);
        if (!note || note.trim().length < 5) return;
        setBusy(true); setMessage('');
        try { await post(`${url}/${p.uuid}/review`, { decision, note: note.trim() });
            setMessage('Review recorded. Campaign approval does not approve or schedule social posts.'); await load();
        } catch (e) { setMessage(e.message); } finally { setBusy(false); }
    };
    const makeDraft = async (proposal) => {
        const input = drafts[proposal.uuid] || { title: proposal.title, copy_text: '' };
        if (input.copy_text.trim().length < 20) { setMessage('Editorial copy must be at least 20 characters.'); return; }
        setBusy(true); setMessage('');
        try {
            const r = await fetch(`${url}/${proposal.uuid}/draft`, {
                method: 'POST', credentials: 'same-origin', headers: {
                    Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(),
                }, body: JSON.stringify(input),
            });
            const j = await r.json();
            if (!r.ok) throw new Error(j?.message || Object.values(j?.errors || {}).flat()[0] || 'Draft creation failed');
            setDraftResponses(prev => ({ ...prev, [proposal.uuid]: j?.data?.content_item_uuid || 'created' }));
            setMessage('Editorial draft created. Platform targeting and independent content approval are still required.');
        } catch (e) { setMessage(e.message); } finally { setBusy(false); }
    };
    const toggle = p => setForm(f => ({ ...f, platforms: f.platforms.includes(p) ? f.platforms.filter(x => x !== p) : [...f.platforms, p] }));
    return <section className="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-card" aria-label="AI campaign proposals">
        <h2 className="text-lg font-semibold">AI campaign proposals</h2>
        <p className="mt-2 text-sm text-muted-foreground">Generate an editorial campaign outline using approved brand context. A different authorized reviewer must approve it. Every proposed post still requires its own draft and approval.</p>
        {usage && <p className="mt-2 text-sm text-muted-foreground" role="status">AI requests today (UTC): {usage.succeeded} completed, {usage.reserved} processing, {usage.remaining} of {usage.limit} remaining.</p>}
        {canCreate && <form onSubmit={submit} className="mt-4 grid gap-3">
            <label className="text-sm">Campaign title<input className={field} value={form.title} minLength={5} maxLength={180} required onChange={e=>setForm({...form,title:e.target.value})}/></label>
            <label className="text-sm">Objective<input className={field} value={form.objective} minLength={5} maxLength={180} required onChange={e=>setForm({...form,objective:e.target.value})}/></label>
            <label className="text-sm">Target audience<input className={field} value={form.audience} minLength={3} maxLength={200} required onChange={e=>setForm({...form,audience:e.target.value})}/></label>
            <label className="text-sm">Duration (days)<input type="number" className={field} min={1} max={30} value={form.duration_days} onChange={e=>setForm({...form,duration_days:Number(e.target.value)})}/></label>
            <fieldset><legend className="text-sm">Platforms</legend><div className="mt-2 flex flex-wrap gap-3">{providers.map(p=><label key={p} className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.platforms.includes(p)} onChange={()=>toggle(p)}/>{p}</label>)}</div></fieldset>
            <label className="flex items-start gap-2 text-sm"><input type="checkbox" checked={form.use_provider_evidence}
                onChange={e=>setForm({...form,use_provider_evidence:e.target.checked})}/>
                Include matched, API-observed per-platform performance evidence where available (requires at least three matched posts on a selected platform; never proves ROI or causality)
            </label>
            <Button type="submit" disabled={busy || !form.platforms.length}>{busy ? 'Working…' : 'Generate campaign proposal'}</Button>
        </form>}
        {message && <p role="status" className="mt-3 text-sm">{message}</p>}
        <div className="mt-4 max-h-[32rem] space-y-4 overflow-auto">{items.map(p=><article key={p.uuid} className="rounded-lg border border-border p-3">
            <div className="text-xs text-muted-foreground">{p.status} · {p.duration_days} days · {p.platforms.join(', ')}</div>
            <h3 className="mt-1 font-semibold">{p.title}</h3><p className="text-xs text-muted-foreground">{p.objective} · {p.audience}</p>
            {p.provider_evidence_hash && <p className="mt-2 text-xs text-muted-foreground">Evidence-bound draft · digest {p.provider_evidence_hash.slice(0,12)}… · {p.provider_evidence_snapshot?.observations?.length??0} verified provider trend(s). Independent review required; no causal attribution.</p>}
            <p className="mt-3 whitespace-pre-wrap text-sm">{p.proposal_text}</p>
            {p.review_note && <p className="mt-2 text-xs text-muted-foreground">Review: {p.review_note}</p>}
            {canCreate && p.status==='APPROVED' && <div className="mt-3 space-y-2 border-t border-border pt-3">
                <p className="text-xs text-muted-foreground">Create a separate editorial draft from this approved outline. Review all claims, write the post copy yourself, and submit that draft for normal content approval.</p>
                {draftResponses[p.uuid] ? <p className="text-sm" role="status">Draft created: {draftResponses[p.uuid]} (not published)</p> : <>
                    <label className="block text-sm">Post title<input className={field} minLength={2} maxLength={180}
                        value={drafts[p.uuid]?.title ?? p.title}
                        onChange={e => setDrafts(prev => ({...prev,[p.uuid]: {...(prev[p.uuid] || {copy_text:''}),title:e.target.value}}))}/></label>
                    <label className="block text-sm">Editor-written post copy<textarea className={field} rows={4} minLength={20} maxLength={10000}
                        placeholder="Write a publishable draft from the approved campaign outline; verify every claim."
                        value={drafts[p.uuid]?.copy_text || ''}
                        onChange={e => setDrafts(prev => ({...prev,[p.uuid]: {...(prev[p.uuid] || {title:p.title}),copy_text:e.target.value}}))}/></label>
                    <Button type="button" disabled={busy} onClick={() => makeDraft(p)}>Create editorial draft (approval required)</Button>
                </>}
            </div>}
            {canReview && p.status==='PROPOSED' && <div className="mt-3 flex gap-2"><Button type="button" disabled={busy} onClick={()=>review(p,'APPROVED')}>Approve outline</Button><Button type="button" variant="outline" disabled={busy} onClick={()=>review(p,'REJECTED')}>Reject</Button></div>}
        </article>)}</div>
    </section>;
}
