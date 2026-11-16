import axios from 'axios';
import { useEffect, useState } from 'react';
import { Button } from '@/Components/ui/button';
export default function OperationalSafetyPanel({ organisationUuid, brandUuid }) {
    const [snapshot, setSnapshot] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const url = `/organisations/${organisationUuid}/brands/${brandUuid}/operational-safety`;
    const refresh = async () => {
        setLoading(true); setError('');
        try { const r = await axios.get(url); setSnapshot(r.data?.data ?? null); }
        catch { setError('Unable to load operator safety counts.'); }
        finally { setLoading(false); }
    };
    useEffect(() => { refresh(); }, [url]);
    return <section className="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-card" aria-label="Operational safety overview">
        <div className="flex items-center justify-between gap-2"><h2 className="text-lg font-semibold">Execution safety · brand overview</h2>
            <Button type="button" variant="outline" size="sm" disabled={loading} onClick={refresh}>Refresh</Button></div>
        <p className="mt-2 text-sm text-muted-foreground">Read-only counters for uncertain publications, expired leases, and failed analytics syncs. No automatic repost, retry or repair is authorized.</p>
        {error && <p role="alert" className="mt-2 text-xs text-destructive">{error}</p>}
        {snapshot && <><p className="mt-3 text-sm">{snapshot.attention_required ? 'Review required' : 'No flagged states detected'} · {snapshot.as_of_utc}</p>
            <dl className="mt-3 grid grid-cols-2 gap-2 md:grid-cols-4">{Object.entries(snapshot.counts ?? {}).map(([name, count]) =>
                <div className="rounded border border-border p-3" key={name}><dt className="text-xs text-muted-foreground">{name.replaceAll('_',' ')}</dt><dd className="mt-1 text-lg font-semibold">{count}</dd></div>)}</dl>
            {snapshot.alert_mail_delivery && <p className="mt-3 text-xs text-muted-foreground" aria-label="Operational alert mail delivery">
                Alert email (opt-in): {snapshot.alert_mail_delivery.enabled ? 'enabled' : 'disabled'} · queued {snapshot.alert_mail_delivery.queued} · sent {snapshot.alert_mail_delivery.sent} · uncertain {snapshot.alert_mail_delivery.uncertain}. Uncertain sends require manual review; no automatic resend.
            </p>}
            {(snapshot.historical_alerts ?? []).length > 0 && <div className="mt-4" aria-label="Operational alert history">
                <h3 className="font-medium">Last recorded alert signals</h3>
                <p className="text-xs text-muted-foreground">Recorded by the opt-in scan worker. A resolved alert reflects observed counts, not provider-confirmed delivery.</p>
                <ul className="mt-2 space-y-1 text-xs">{snapshot.historical_alerts.map((alert) =>
                    <li className="flex items-center justify-between gap-2 rounded border border-border p-2" key={alert.signal}>
                        <span>{alert.signal.replaceAll('_',' ')}</span><span>{alert.state} · {alert.count}</span>
                    </li>)}</ul></div>}
        </>}
    </section>;
}
