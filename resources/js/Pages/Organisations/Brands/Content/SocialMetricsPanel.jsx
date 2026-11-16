import ProviderMetricSyncPanel from './ProviderMetricSyncPanel';
import ProviderPerformanceInsightsPanel from './ProviderPerformanceInsightsPanel';
import ProviderMetricSyncSchedulePanel from './ProviderMetricSyncSchedulePanel';
import { useCallback, useEffect, useRef, useState } from 'react';

const providerOptions = ['facebook','instagram','linkedin','x','tiktok','youtube'];
const fields = ['impressions','reach','likes','comments','shares','clicks'];
const control = 'mt-1 w-full rounded border border-input bg-background px-2 py-2 text-sm';
/** Operator-supplied metrics are clearly distinct from verified provider API reporting. */
export default function SocialMetricsPanel({organisationUuid,brandUuid,canSubmit=false}) {
    const base = `/api/v1/organisations/${encodeURIComponent(organisationUuid)}/brands/${encodeURIComponent(brandUuid)}/content/social-metrics`;
    const [range,setRange]=useState(()=>({from:new Date(Date.now()-29*86400000).toISOString().slice(0,10),to:new Date().toISOString().slice(0,10)}));
    const [form,setForm]=useState({provider:'instagram',account_reference:'',external_post_reference:'',observed_at:'',source_type:'MANUAL_EXPORT',source_reference:'',...Object.fromEntries(fields.map(k=>[k,0]))});
    const [report,setReport]=useState(null); const [error,setError]=useState('');
    const pendingKey=useRef(null);
    const [busy,setBusy]=useState(false); const [notice,setNotice]=useState('');
    const refresh=useCallback(async(signal)=>{
        const response=await fetch(`${base}?${new URLSearchParams(range)}`,{credentials:'same-origin',headers:{Accept:'application/json'},signal});
        if(!response.ok)throw new Error(`Metrics unavailable (${response.status})`);
        const body=await response.json(); setReport(body.data??body);
    },[base,range.from,range.to]);
    useEffect(()=>{const controller=new AbortController();setError('');refresh(controller.signal).catch(e=>{if(e.name!=='AbortError')setError(e.message)});return()=>controller.abort()},[refresh]);
    const update=(name,value)=>{pendingKey.current=null;setForm(old=>({...old,[name]:value}));};
    async function submit(event){
        event.preventDefault();setBusy(true);setError('');setNotice('');
        try {
            const csrf=document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const payload={...form,observed_at:new Date(form.observed_at).toISOString(),submission_key:pendingKey.current??(pendingKey.current=crypto.randomUUID()+crypto.randomUUID())};
            const response=await fetch(base,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json',...(csrf?{'X-CSRF-TOKEN':csrf}:{})},body:JSON.stringify(payload)});
            if(!response.ok)throw new Error(`Import failed (${response.status}). Check permissions and fields.`);
            setNotice('Metrics recorded as unverified operator-provided evidence.');pendingKey.current=null;await refresh();
        }catch(e){setError(e.message)}finally{setBusy(false)}
    }
    return <section className="rounded-xl border border-border bg-card p-5 text-card-foreground" aria-labelledby="social-metrics-heading">
        <h2 id="social-metrics-heading" className="text-lg font-semibold">Social metrics evidence</h2>
        <p className="mt-1 text-sm text-muted-foreground">Manual entries: Not verified with social networks. Provider API observations are shown separately; neither proves audited reach or ROI.</p>
        <div className="mt-3 flex flex-wrap gap-3 text-sm">
            <label>From UTC<input aria-label="Social metrics from" type="date" className={control} value={range.from} onChange={e=>setRange(old=>({...old,from:e.target.value}))}/></label>
            <label>To UTC<input aria-label="Social metrics to" type="date" className={control} value={range.to} onChange={e=>setRange(old=>({...old,to:e.target.value}))}/></label>
        </div>
        {error&&<p role="alert" className="mt-2 text-sm text-destructive">{error}</p>}
        {notice&&<p role="status" className="mt-2 text-sm">{notice}</p>}
        {report?.truncated&&<p role="alert" className="mt-3 text-sm text-destructive">More than 500 observations. Totals are withheld to avoid incomplete reporting. Narrow the date range.</p>}
        {report&&!report.truncated&&<div className="mt-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">{fields.map(key=><div key={key} className="rounded border border-border p-3"><div className="text-xs text-muted-foreground">{key}</div><div className="text-lg font-semibold tabular-nums">{report.totals?.[key]??'—'}</div></div>)}</div>}
        {report&&<p className="mt-2 text-xs text-muted-foreground">{report.unique_posts_in_sample??0} distinct manual-report posts; each uses the latest observation. Source: {report.verification}.</p>}
        <section className="mt-5 border-t border-border pt-4" aria-label="API-sourced performance">
            <h3 className="font-semibold">Provider API performance (separate from manual metrics)</h3>
            {report?.provider_api?.truncated&&<p role="alert" className="mt-2 text-sm text-destructive">Provider observation limit reached. API aggregates withheld; narrow the dates.</p>}
            {report?.provider_api&&!report.provider_api.truncated&&<div className="mt-3 grid gap-3 sm:grid-cols-2">{Object.entries(report.provider_api.provider_breakdown??{}).map(([provider,values])=><div key={provider} className="rounded border border-border p-3"><h4 className="font-medium">{provider==='x'?'X':provider==='facebook'?'Facebook Page':provider==='tiktok'?'TikTok':provider==='instagram'?'Instagram':'YouTube'} · {values.unique_posts} observed posts</h4><p className="mt-1 text-xs text-muted-foreground">{values.metric_definition}</p><div className="mt-2 flex gap-4 text-xs">{['views','likes','comments'].map(k=><span key={k}>{k==='views'?(provider==='x'?'Public impressions':provider==='facebook'?'Media views':'Views'):k}: <strong>{values.totals?.[k]??'Unknown'}</strong></span>)}</div></div>)}</div>}
            <p className="mt-2 text-xs text-muted-foreground">Source: {report?.provider_api?.verification??'Not available'} · {report?.provider_api?.unique_posts_in_sample??0} distinct posts in range. Platform metrics differ: YouTube video views are not X public impressions or unique reach. No cross-provider totals or ROI are asserted.</p>
        </section>
        <ProviderMetricSyncPanel organisationUuid={organisationUuid} brandUuid={brandUuid} canRequest={canSubmit}/>
        <ProviderPerformanceInsightsPanel organisationUuid={organisationUuid} brandUuid={brandUuid}/>
        <ProviderMetricSyncSchedulePanel organisationUuid={organisationUuid} brandUuid={brandUuid} canManage={canSubmit}/>
        {canSubmit&&<form className="mt-5 space-y-3 border-t border-border pt-4" onSubmit={submit}>
            <h3 className="font-semibold">Record an external metrics snapshot</h3>
            <div className="grid gap-3 sm:grid-cols-3">
                <label className="text-sm">Platform<select className={control} value={form.provider} onChange={e=>update('provider',e.target.value)}>{providerOptions.map(p=><option key={p} value={p}>{p}</option>)}</select></label>
                <label className="text-sm">Account reference<input required pattern="[A-Za-z0-9._:-]+" maxLength={160} className={control} value={form.account_reference} onChange={e=>update('account_reference',e.target.value)}/></label>
                <label className="text-sm">Post reference<input required pattern="[A-Za-z0-9._:-]+" maxLength={190} className={control} value={form.external_post_reference} onChange={e=>update('external_post_reference',e.target.value)}/></label>
                <label className="text-sm">Observation time<input required type="datetime-local" className={control} value={form.observed_at} onChange={e=>update('observed_at',e.target.value)}/></label>
                <label className="text-sm">Evidence type<select className={control} value={form.source_type} onChange={e=>update('source_type',e.target.value)}><option value="MANUAL_EXPORT">Manually transcribed export</option><option value="PROVIDER_SCREENSHOT">Provider screenshot (reference only)</option></select></label>
                <label className="text-sm">Evidence reference<input required maxLength={240} className={control} value={form.source_reference} onChange={e=>update('source_reference',e.target.value)} placeholder="Export reference or internal evidence ID"/></label>
                {fields.map(key=><label key={key} className="text-sm">{key}<input required type="number" min="0" max="1000000000000" className={control} value={form[key]} onChange={e=>update(key,Number(e.target.value))}/></label>)}
            </div>
            <button disabled={busy} className="rounded bg-primary px-4 py-2 text-sm text-primary-foreground disabled:opacity-50" type="submit">{busy?'Recording…':'Record unverified snapshot'}</button>
        </form>}
    </section>;
}
