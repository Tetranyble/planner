import { useEffect, useState } from 'react';
import { Button } from '@/Components/ui/button';
const field = 'mt-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground';
const platforms = ['facebook','instagram','linkedin','x','tiktok','youtube'];
export default function AiSuggestionsPanel({ organisationUuid, brandUuid, canCreate }) {
    const url = `/api/v1/organisations/${organisationUuid}/brands/${brandUuid}/content/ai-suggestions`;
    const [items, setItems] = useState([]);
    const [topic, setTopic] = useState('');
    const [platform, setPlatform] = useState('linkedin');
    const [campaign, setCampaign] = useState('');
    const [busy, setBusy] = useState(false);
    const [claimReview, setClaimReview] = useState(null);
    const [usage, setUsage] = useState(null);
    const usageUrl = `/api/v1/organisations/${organisationUuid}/brands/${brandUuid}/content/ai-usage`;
    const loadUsage = async () => {
        try { const r = await fetch(usageUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const j = await r.json(); if (r.ok) setUsage(j?.data ?? null);
        } catch { /* usage endpoint remains authoritative on POST */ }
    };
    useEffect(() => { loadUsage(); }, [usageUrl]);
    const [message, setMessage] = useState('');
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const fetchSuggestions = async () => {
        try {
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }});
            const json = await response.json();
            if (response.ok) setItems(json?.data?.data ?? []);
        } catch { setMessage('Unable to load AI suggestions.'); }
    };
    useEffect(() => { fetchSuggestions(); }, [url]);
    const send = async (path, body) => {
        const response = await fetch(path, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(body),
        });
        const json = await response.json();
        if (!response.ok) throw new Error(json?.message || Object.values(json?.errors || {}).flat()[0] || 'Request failed');
        return json;
    };
    const generate = async (event) => {
        event.preventDefault(); setBusy(true); setMessage('');
        try { await send(url, { topic, platform, campaign_name: campaign || null });
            setTopic(''); setMessage('Suggestion saved for human review.'); await fetchSuggestions(); await loadUsage();
        } catch (error) { setMessage(error.message); } finally { await loadUsage(); setBusy(false); }
    };
    const inspectClaims = async suggestion => {
        setBusy(true); setMessage('');
        try {
            const response = await fetch(`${url}/${suggestion.uuid}/claim-review`, {
                credentials: 'same-origin', headers: { Accept: 'application/json' },
            });
            const json = await response.json();
            if (!response.ok) throw new Error(json?.message || 'Unable to assess saved copy.');
            setClaimReview(json?.data ?? null);
        } catch (error) { setMessage(error.message); }
        finally { setBusy(false); }
    };
    const promote = async (suggestion) => {
        const title = window.prompt('Title for the new editable content draft', suggestion.topic.slice(0, 140));
        if (!title?.trim()) return;
        setBusy(true); setMessage('');
        try { await send(`${url}/${suggestion.uuid}/promote`, { title: title.trim() });
            setMessage('Draft created. It still requires editorial review and approval.'); await fetchSuggestions(); await loadUsage();
        } catch (error) { setMessage(error.message); } finally { setBusy(false); }
    };
    return <section className="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-card" aria-label="AI copy suggestions">
        <h2 className="text-lg font-semibold">AI copy suggestions</h2>
        <p className="mt-2 text-sm text-muted-foreground">Opt-in assistance using your brand positioning and approved claims. Check every factual assertion before approval. Suggestions never publish automatically.</p>
        {usage && <p className="mt-2 text-sm text-muted-foreground" role="status">AI requests today (UTC): {usage.succeeded} completed, {usage.reserved} processing, {usage.remaining} of {usage.limit} remaining.</p>}
        {canCreate && <form onSubmit={generate} className="mt-4 grid gap-3">
            <label className="text-sm">Campaign (optional)<input value={campaign} onChange={e=>setCampaign(e.target.value)} className={field} maxLength={180}/></label>
            <label className="text-sm">Topic<input value={topic} onChange={e=>setTopic(e.target.value)} className={field} minLength={5} maxLength={500} required/></label>
            <label className="text-sm">Platform<select value={platform} onChange={e=>setPlatform(e.target.value)} className={field}>{platforms.map(p=><option key={p} value={p}>{p}</option>)}</select></label>
            <Button type="submit" disabled={busy}>{busy ? 'Working…' : 'Generate suggestion'}</Button>
        </form>}
        {message && <p className="mt-3 text-sm" role="status">{message}</p>}
        <div className="mt-4 max-h-96 space-y-4 overflow-auto" aria-label="Saved suggestions">{items.map(s=><article key={s.uuid} className="rounded-lg border border-border p-3">
            <div className="text-xs text-muted-foreground">{s.platform} · {s.status} · {s.campaign_name || 'No campaign'}</div>
            <h3 className="mt-1 text-sm font-semibold">{s.topic}</h3>
            <p className="mt-2 whitespace-pre-wrap text-sm">{s.text}</p>
            <div className="mt-3 flex flex-wrap gap-2">
                <Button type="button" variant="outline" disabled={busy} onClick={()=>inspectClaims(s)}>Inspect claim risks</Button>
                {canCreate && s.status==='SUGGESTED' && <Button type="button" disabled={busy} onClick={()=>promote(s)}>Convert to draft</Button>}
            </div>
            {claimReview?.suggestion_uuid===s.uuid && <div className="mt-2 rounded bg-muted p-3 text-xs" aria-live="polite">
                <p className="font-semibold">Editorial claim check — human review required</p>
                <p>Risk flags: {(claimReview.risk_codes ?? []).join(', ') || 'None detected by these limited patterns'}</p>
                <p>Exact matches to approved wording: {(claimReview.matched_approved_wording ?? []).length}</p>
                <p className="mt-1 text-muted-foreground">{claimReview.limitations} No factual claim is certified by this check.</p>
            </div>}
        </article>)}</div>
    </section>;
}
