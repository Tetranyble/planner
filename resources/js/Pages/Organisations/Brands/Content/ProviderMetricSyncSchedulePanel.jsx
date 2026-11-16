import { useCallback, useEffect, useState } from 'react';

export default function ProviderMetricSyncSchedulePanel({organisationUuid,brandUuid,canManage=false}) {
    const endpoint=`/api/v1/organisations/${encodeURIComponent(organisationUuid)}/brands/${encodeURIComponent(brandUuid)}/content/social-metrics/provider-sync-schedule`;
    const [value,setValue]=useState(null);const [busy,setBusy]=useState(false);
    const [message,setMessage]=useState('');const [error,setError]=useState('');
    const load=useCallback(async(signal)=>{
        const reply=await fetch(endpoint,{credentials:'same-origin',headers:{Accept:'application/json'},signal});
        if(!reply.ok)throw new Error(`Schedule unavailable (${reply.status})`);
        const body=await reply.json();setValue(body.data??body);
    },[endpoint]);
    useEffect(()=>{const controller=new AbortController();load(controller.signal).catch(e=>{if(e.name!=='AbortError')setError(e.message)});return()=>controller.abort()},[load]);
    async function save() {
        setBusy(true);setError('');setMessage('');
        try {
            const csrf=document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const reply=await fetch(endpoint,{method:'PUT',credentials:'same-origin',headers:{
                'Content-Type':'application/json',Accept:'application/json',...(csrf?{'X-CSRF-TOKEN':csrf}:{})
            },body:JSON.stringify({enabled:Boolean(value.schedule.enabled),utc_hour:Number(value.schedule.utc_hour),
                max_posts_per_day:Number(value.schedule.max_posts_per_day),version:value.schedule.version})});
            if(!reply.ok)throw new Error(`Schedule update failed (${reply.status}); refresh before retrying.`);
            setMessage('Schedule saved. Automation requires both independent deployment switches to be enabled.');
            await load();
        }catch(e){setError(e.message)}finally{setBusy(false)}
    }
    const schedule=value?.schedule;
    return <section className="mt-4 rounded-lg border border-border bg-card p-4" aria-label="Provider metric sync schedule">
        <h3 className="font-semibold">Daily YouTube metric sync</h3>
        <p className="mt-1 text-xs text-muted-foreground">Opt-in brand-specific report collection; read-only Google requests, at most 20 recent confirmed posts per UTC day. Never publishes anything.</p>
        {value&&<p className="mt-2 text-xs text-muted-foreground">Collector: {value.global_enabled?'enabled':'disabled'} · auto-queue: {value.autoqueue_enabled?'enabled':'disabled'} · last queued: {schedule.last_queued_day??'Never'}</p>}
        {error&&<p role="alert" className="mt-2 text-sm text-destructive">{error}</p>}
        {message&&<p role="status" className="mt-2 text-sm">{message}</p>}
        {schedule&&<div className="mt-3 grid gap-3 sm:grid-cols-3">
            <label className="text-sm">Daily schedule
                <select disabled={!canManage} className="mt-1 w-full rounded border border-input bg-background p-2" value={schedule.enabled?'yes':'no'}
                    onChange={e=>setValue(v=>({...v,schedule:{...v.schedule,enabled:e.target.value==='yes'}}))}>
                    <option value="no">Paused</option><option value="yes">Enabled</option>
                </select></label>
            <label className="text-sm">UTC hour (0–23)<input className="mt-1 w-full rounded border border-input bg-background p-2" type="number" min="0" max="23"
                disabled={!canManage} value={schedule.utc_hour} onChange={e=>setValue(v=>({...v,schedule:{...v.schedule,utc_hour:Number(e.target.value)}}))}/></label>
            <label className="text-sm">Max confirmed posts/day<input className="mt-1 w-full rounded border border-input bg-background p-2" type="number" min="1" max="20"
                disabled={!canManage} value={schedule.max_posts_per_day} onChange={e=>setValue(v=>({...v,schedule:{...v.schedule,max_posts_per_day:Number(e.target.value)}}))}/></label>
        </div>}
        {canManage&&schedule&&<button type="button" disabled={busy} onClick={save}
            className="mt-3 rounded bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50">{busy?'Saving…':'Save daily sync policy'}</button>}
    </section>;
}
