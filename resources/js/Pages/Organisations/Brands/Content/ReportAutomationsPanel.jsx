import { useEffect, useState } from 'react';
import { Button } from '@/Components/ui/button';

/** Provenance-separated read-only metric report automation. Never creates posts. */
export default function ReportAutomationsPanel({ organisationUuid, brandUuid, canManage = false }) {
    const base = `/api/v1/organisations/${encodeURIComponent(organisationUuid)}/brands/${encodeURIComponent(brandUuid)}/content/report-automations`;
    const today = new Date().toISOString().slice(0, 10);
    const [data, setData] = useState({ rules: [], runs: [] });
    const [form, setForm] = useState({ name: '', from: today, to: today, max_runs_per_day: 5, trigger_kind: 'MANUAL', hour_utc: 7, lookback_days: 7 });
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    async function request(path, method = 'GET', payload) {
        const csrf = document.cookie.split('; ').find(v => v.startsWith('XSRF-TOKEN='))?.split('=')[1];
        const res = await fetch(path, { method, credentials: 'same-origin', headers: {
            Accept: 'application/json', 'Content-Type': 'application/json',
            ...(csrf ? { 'X-XSRF-TOKEN': decodeURIComponent(csrf) } : {})
        }, ...(payload ? { body: JSON.stringify(payload) } : {}) });
        const body = await res.json();
        if (!res.ok) throw new Error(body.message || 'Request failed');
        return body.data ?? body;
    }
    async function reload() { try { setData(await request(base)); } catch (e) { setError(e.message); } }
    useEffect(() => { reload(); }, [base]);
    async function act(fn) {
        setBusy(true); setError('');
        try { await fn(); await reload(); } catch(e) { setError(e.message); } finally { setBusy(false); }
    }
    return <section className="rounded-xl border border-border bg-card p-5 text-card-foreground" aria-labelledby="report-automations-heading">
        <h2 id="report-automations-heading" className="text-lg font-semibold">Report automations</h2>
        <p className="mt-1 text-sm text-muted-foreground">Manual or opt-in daily UTC reports preserve unverified operator metrics separately from read-only provider API snapshots. Manual totals are USER_REPORTED_UNVERIFIED; provider counts are PROVIDER_API_OBSERVED when actually fetched. Reports cannot publish content or contact social networks.</p>
        {error && <p role="alert" className="mt-2 text-sm text-destructive">{error}</p>}
        {canManage && <form className="mt-4 grid gap-3 sm:grid-cols-5" onSubmit={e => { e.preventDefault(); act(() => request(base,'POST',form)); }}>
            <label className="text-sm sm:col-span-2">Rule name<input required minLength={3} maxLength={120} className="mt-1 w-full rounded-md border border-input bg-background p-2" value={form.name} onChange={e=>setForm(v=>({...v,name:e.target.value}))}/></label>
            <label className="text-sm">Trigger<select className="mt-1 w-full rounded-md border border-input bg-background p-2" value={form.trigger_kind} onChange={e=>setForm(v=>({...v,trigger_kind:e.target.value}))}><option value="MANUAL">Manual</option><option value="DAILY_UTC">Daily UTC</option></select></label>
            {form.trigger_kind === 'MANUAL' && <label className="text-sm">From UTC<input required type="date" className="mt-1 w-full rounded-md border border-input bg-background p-2" value={form.from} onChange={e=>setForm(v=>({...v,from:e.target.value}))}/></label>}
            {form.trigger_kind === 'MANUAL' && <label className="text-sm">To UTC<input required type="date" className="mt-1 w-full rounded-md border border-input bg-background p-2" value={form.to} onChange={e=>setForm(v=>({...v,to:e.target.value}))}/></label>}
            {form.trigger_kind === 'DAILY_UTC' && <><label className="text-sm">Run at (UTC hour)<input required type="number" min="0" max="23" className="mt-1 w-full rounded-md border border-input bg-background p-2" value={form.hour_utc} onChange={e=>setForm(v=>({...v,hour_utc:Number(e.target.value)}))}/></label><label className="text-sm">Completed-day lookback<input required type="number" min="1" max="30" className="mt-1 w-full rounded-md border border-input bg-background p-2" value={form.lookback_days} onChange={e=>setForm(v=>({...v,lookback_days:Number(e.target.value)}))}/></label></>}
            <label className="text-sm">Daily cap<input required type="number" min="1" max="20" className="mt-1 w-full rounded-md border border-input bg-background p-2" value={form.max_runs_per_day} onChange={e=>setForm(v=>({...v,max_runs_per_day:Number(e.target.value)}))}/></label>
            <Button type="submit" disabled={busy} className="sm:col-span-5">Create report rule</Button>
        </form>}
        <h3 className="mt-5 font-medium">Rules</h3>
        <ul className="mt-2 space-y-2">{data.rules.map(rule=><li key={rule.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border p-3 text-sm">
            <span>{rule.name} · {rule.status} · {rule.max_runs_per_day} runs/day · {rule.trigger_kind === 'DAILY_UTC' ? `Daily at ${String(rule.schedule?.hour_utc ?? 0).padStart(2,'0')}:00 UTC, previous ${rule.schedule?.lookback_days ?? 1} days` : 'Manual'}</span>
            {canManage && <div className="flex gap-2">{rule.status!=='RETIRED' && <Button disabled={busy} variant="outline" size="sm" onClick={()=>act(()=>request(`${base}/${rule.id}/status`,'POST',{status:rule.status==='ENABLED'?'PAUSED':'ENABLED'}))}>{rule.status==='ENABLED'?'Pause':'Enable'}</Button>}
                {rule.status==='ENABLED' && rule.trigger_kind==='MANUAL' && <Button disabled={busy} size="sm" onClick={()=>act(()=>request(`${base}/${rule.id}/run`,'POST',{idempotency_key:crypto.randomUUID()+crypto.randomUUID()}))}>Generate report</Button>}</div>}
        </li>)}</ul>
        <h3 className="mt-5 font-medium">Recent report runs</h3>
        <ul className="mt-2 space-y-2">{data.runs.map(run=><li key={run.uuid} className="rounded-lg border border-border p-3 text-sm">
            <p>{run.created_at} · {run.status} · {run.source}</p>
            <p className="text-muted-foreground">{run.aggregates_complete ? `Manual evidence — impressions: ${run.totals?.impressions ?? 'Not available'} · clicks: ${run.totals?.clicks ?? 'Not available'}` : 'Manual totals withheld: source sample incomplete'}</p>
            <p className="mt-1 text-xs text-muted-foreground">Provider API: {run.provider_api?.verification??'NO_PROVIDER_API_OBSERVATIONS'} · YouTube views: {run.provider_api?.totals?.views??'Not available'} · provider-sourced post count: {run.provider_api?.unique_posts_in_sample??0}{run.provider_api?.truncated?' (totals withheld: sample incomplete)':''}</p>
        </li>)}</ul>
    </section>;
}
