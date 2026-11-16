import { useCallback, useEffect, useState } from 'react';

/** API observations are displayed separately from manual evidence and do not assert ROI. */
export default function ProviderMetricSyncPanel({organisationUuid, brandUuid, canRequest=false}) {
    const base = `/api/v1/organisations/${encodeURIComponent(organisationUuid)}/brands/${encodeURIComponent(brandUuid)}/content/social-metrics/provider-syncs`;
    const [state,setState]=useState(null);
    const [picked,setPicked]=useState([]);
    const [error,setError]=useState('');
    const [message,setMessage]=useState('');
    const [busy,setBusy]=useState(false);
    const refresh=useCallback(async signal => {
        const reply=await fetch(base,{credentials:'same-origin',headers:{Accept:'application/json'},signal});
        if(!reply.ok)throw new Error(`Provider sync queue unavailable (${reply.status})`);
        const body=await reply.json();setState(body.data??body);
    },[base]);
    useEffect(()=>{
        const controller=new AbortController();
        refresh(controller.signal).catch(e=>{if(e.name!=='AbortError')setError(e.message)});
        return()=>controller.abort();
    },[refresh]);
    async function queue() {
        setError('');setMessage('');setBusy(true);
        try {
            const csrf=document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const reply=await fetch(base,{method:'POST',credentials:'same-origin',headers:{
                'Content-Type':'application/json',Accept:'application/json',...(csrf?{'X-CSRF-TOKEN':csrf}:{})
            },body:JSON.stringify({published_post_ids:picked})});
            if(!reply.ok)throw new Error(`Provider sync request rejected (${reply.status})`);
            setPicked([]);setMessage('Read-only metric sync queued. A worker must be enabled to fetch API observations.');
            await refresh();
        }catch(e){setError(e.message)}finally{setBusy(false)}
    }
    return <section className="mt-6 rounded-lg border border-border bg-card p-4" aria-label="Provider metric sync">
        <h3 className="font-semibold">Provider API metric observations</h3>
        <p className="mt-1 text-xs text-muted-foreground">
            Read-only metrics from confirmed YouTube, X, Facebook Page, TikTok and Instagram posts. Platform views, impressions, reach and engagement are not interchangeable or summed into a cross-provider total. These observations do not establish ROI.
        </p>
        {state && <p className="mt-2 text-xs text-muted-foreground">Worker: {state.released?'Enabled by deployment':'Disabled by deployment'} · X read access: {state.x_released?'Enabled':'Disabled (separate cost/permission gate)'} · Facebook: {state.facebook_released?'Enabled':'Disabled'} · TikTok: {state.tiktok_released?'Enabled':'Disabled'} · Instagram: {state.instagram_released?'Enabled':'Disabled'} · latest 50 confirmed publications</p>}
        {error && <p role="alert" className="mt-2 text-sm text-destructive">{error}</p>}
        {message && <p role="status" className="mt-2 text-sm">{message}</p>}
        {state?.published_posts?.length ? <div className="mt-3 max-h-52 space-y-2 overflow-auto">
            {state.published_posts.map(post=><label key={post.id} className="flex items-center gap-2 text-sm">
                {canRequest&&<input type="checkbox" checked={picked.includes(post.id)} onChange={event=>setPicked(old=>event.target.checked?[...old,post.id]:old.filter(id=>id!==post.id))} />}
                {post.provider.toUpperCase()} {post.external_post_id} · publication #{post.id}
            </label>)}
        </div> : <p className="mt-2 text-sm text-muted-foreground">No eligible confirmed YouTube, X, Facebook Page, TikTok, or Instagram publications yet.</p>}
        {canRequest && <button type="button" onClick={queue} disabled={busy||!state?.released||picked.length<1||picked.length>20}
            className="mt-3 rounded bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50">
            {busy?'Queueing…':`Queue API metric sync (${picked.length}/20)`}
        </button>}
        {!!state?.runs?.length&&<div className="mt-4">
            <h4 className="text-sm font-semibold">Sync history</h4>
            <ul className="mt-2 max-h-44 space-y-1 overflow-auto text-xs text-muted-foreground">
                {state.runs.map(run=><li key={run.id}>{run.provider?.toUpperCase()} post #{run.post_id} · {run.sync_day} · {run.state}
                    {run.outcome_code?` · ${run.outcome_code}`:''}</li>)}
            </ul>
        </div>}
    </section>;
}
