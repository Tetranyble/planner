import { useEffect, useState } from 'react';

/** Internal operational KPIs only; no fabricated social engagement. */
export default function OperationsDashboardPanel({ organisationUuid, brandUuid }) {
    const [range, setRange] = useState(() => {
        const end = new Date(); const start = new Date(end.getTime() - 29 * 86400000);
        return { from: start.toISOString().slice(0, 10), to: end.toISOString().slice(0, 10) };
    });
    const [data, setData] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    useEffect(() => {
        const controller = new AbortController();
        const params = new URLSearchParams(range);
        setLoading(true); setError('');
        fetch(`/api/v1/organisations/${encodeURIComponent(organisationUuid)}/brands/${encodeURIComponent(brandUuid)}/content/operations-dashboard?${params}`, {
            credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal,
        }).then(async response => { if (!response.ok) throw new Error(`Dashboard request failed (${response.status})`); return response.json(); })
            .then(result => setData(result.data ?? result))
            .catch(err => { if (err.name !== 'AbortError') setError(err.message); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [organisationUuid, brandUuid, range.from, range.to]);
    const counts = data?.counts;
    const cards = counts ? [
        ['Drafts created', counts.content?.total], ['Platform variants', counts.targets?.total],
        ['Approval requests', counts.approvals?.total], ['Held / publication plans', counts.publication_plans?.total],
        ['Overdue held plans (all dates)', counts.publication_plans?.overdue_held],
        ['Delivery attempts', counts.attempts?.total],
    ] : [];
    return <section className="rounded-xl border border-border bg-card p-5 text-card-foreground" aria-labelledby="operations-metrics-heading">
        <h2 id="operations-metrics-heading" className="text-lg font-semibold">Content operations overview</h2>
        <p className="mt-1 text-sm text-muted-foreground">Internal Planner workflow metrics, not social reach, impressions, revenue or ROI.</p>
        <div className="mt-3 flex flex-wrap gap-3 text-sm">
            <label>From (UTC) <input aria-label="Dashboard from" className="ml-2 rounded border border-input bg-background p-1" type="date" value={range.from} onChange={e => setRange(previous => ({...previous, from:e.target.value}))} /></label>
            <label>To (UTC) <input aria-label="Dashboard to" className="ml-2 rounded border border-input bg-background p-1" type="date" value={range.to} onChange={e => setRange(previous => ({...previous, to:e.target.value}))} /></label>
        </div>
        {loading && <p role="status" className="mt-3 text-sm">Loading metrics…</p>}
        {error && <p role="alert" className="mt-3 text-sm text-destructive">{error}</p>}
        {counts && <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{cards.map(([label,value]) => <div key={label} className="rounded-lg border border-border p-3"><div className="text-xs text-muted-foreground">{label}</div><div className="mt-1 text-2xl font-semibold tabular-nums">{value ?? 0}</div></div>)}</div>}
        {counts && <div className="mt-4 grid gap-3 sm:grid-cols-2"><StatusTable title="Approval status" values={counts.approvals?.by_status} /><StatusTable title="Publication plan states" values={counts.publication_plans?.by_state} /></div>}
        <p className="mt-3 text-xs text-muted-foreground">{data?.provider_engagement_note ?? 'Platform engagement is not available.'} Overdue plans count all historical overdue holds.</p>
    </section>;
}
function StatusTable({title,values}) { return <div className="rounded-lg border border-border p-3"><h3 className="text-sm font-semibold">{title}</h3><dl className="mt-2 text-sm">{Object.entries(values ?? {}).map(([key,value]) => <div key={key} className="flex justify-between gap-4"><dt>{key}</dt><dd className="tabular-nums">{value}</dd></div>)}</dl></div>; }
