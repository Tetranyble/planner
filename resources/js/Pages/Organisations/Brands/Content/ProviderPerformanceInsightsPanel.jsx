import { useCallback, useEffect, useState } from 'react';

export default function ProviderPerformanceInsightsPanel({organisationUuid,brandUuid}) {
    const [value,setValue]=useState(null);const [error,setError]=useState('');
    const url=`/api/v1/organisations/${encodeURIComponent(organisationUuid)}/brands/${encodeURIComponent(brandUuid)}/content/social-metrics/performance-insights`;
    const refresh=useCallback(async(signal)=>{
        const reply=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},signal});
        if(!reply.ok)throw new Error(`Performance insights unavailable (${reply.status})`);
        const data=await reply.json();setValue(data.data??data);
    },[url]);
    useEffect(()=>{const controller=new AbortController();refresh(controller.signal).catch(e=>{if(e.name!=='AbortError')setError(e.message)});return()=>controller.abort();},[refresh]);
    return <section className="mt-4 rounded-lg border border-border bg-card p-4" aria-label="Provider-based performance signals">
        <div className="flex items-center justify-between gap-3"><h3 className="font-semibold">Performance signals</h3>
            <button className="text-xs text-primary underline disabled:opacity-50" type="button" onClick={()=>refresh().catch(e=>setError(e.message))}>Refresh</button></div>
        <p className="mt-1 text-xs text-muted-foreground">Compares the same confirmed posts before and after a 14-day cutoff, separately by platform. X public impressions and YouTube video views are not comparable; results are not causal effects.</p>
        {error&&<p role="alert" className="mt-2 text-sm text-destructive">{error}</p>}
        {value&&<div className="mt-3 space-y-2 text-sm">
            {Object.entries(value.platforms??{}).map(([provider,trend])=><div key={provider} className="rounded border border-border p-3">
                <p className="font-semibold">{provider==='x'?'X':provider==='facebook'?'Facebook Page':provider==='tiktok'?'TikTok':provider==='instagram'?'Instagram':'YouTube'} — {trend.metric_definition}</p>
                <p>Evidence: {trend.status} · matched posts: {trend.sampled_pairs}</p>
                {trend.views&&<p>Matched count: {trend.views.baseline.toLocaleString()} → {trend.views.latest.toLocaleString()}
                    {trend.change_percent!==null?` (${trend.change_percent}% change)`:''}</p>}
                <p>{trend.recommendation}</p>
                <p className="text-xs text-muted-foreground">Source: {trend.source}; editorial review required. No causality, ROI, or automatic optimization.</p>
            </div>)}
        </div>}
    </section>;
}
