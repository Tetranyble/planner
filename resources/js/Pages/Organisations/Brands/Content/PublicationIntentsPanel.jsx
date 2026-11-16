import MediaPublicationPanel from './MediaPublicationPanel';
import PublicationCalendarPanel from './PublicationCalendarPanel';
import RecurringCampaignPanel from './RecurringCampaignPanel';
import PublicationSimulationPanel from './PublicationSimulationPanel';
import PublicationReconciliationPanel from './PublicationReconciliationPanel';
import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { Button } from '@/Components/ui/button';

/** Operator plans and explicitly gated production execution. */
export default function PublicationIntentsPanel({ organisation, brand, item, approvals = [] }) {
    const base = `/api/v1/organisations/${organisation.uuid}/brands/${brand.uuid}/content`;
    const [rows, setRows] = useState([]);
    const [operations, setOperations] = useState(null);
    const [preflight, setPreflight] = useState(null);
    const [history, setHistory] = useState(null);
    const [inspectedState, setInspectedState] = useState(null);
    const [runAt, setRunAt] = useState('');
    const [selectedApprovals, setSelectedApprovals] = useState([]);
    const [selectedJobs, setSelectedJobs] = useState([]);
    const [rescheduleTimes, setRescheduleTimes] = useState({});
    // Stable keys across network retries; rotate only after confirmed completion.
    const pendingKeys = useRef(new Map());
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const load = async () => {
        try {
            const response = await axios.get(`${base}/publication-intents`);
            setRows(response.data?.data ?? []);
            const operational = await axios.get(`${base}/publication-operations`);
            setOperations(operational.data?.data ?? null);
        } catch { setError('Unable to load held publication plans.'); }
    };
    useEffect(() => { load(); }, [base]);
    const approved = approvals.filter(request => request.status === 'APPROVED' && request.target_uuid);
    const create = async (request) => {
        setBusy(true); setError('');
        try {
            if (!runAt) throw new Error('Choose a date and time first.');
            const requestedTime = new Date(runAt);
            if (Number.isNaN(requestedTime.valueOf())) throw new Error('Invalid date and time.');
            // The same key is retained for retries of the same operator action.
            const identity = `${request.target_uuid}:${request.id}:${requestedTime.toISOString()}`;
            const key = pendingKeys.current.get(identity) ?? window.crypto.randomUUID();
            pendingKeys.current.set(identity, key);
            await axios.post(`${base}/${item.uuid}/targets/${request.target_uuid}/approvals/${request.id}/publication-intents`, {
                occurrence_key: key, run_at: requestedTime.toISOString(),
            });
            pendingKeys.current.delete(identity);
            await load();
        } catch (e) { setError(e?.response?.data?.message ?? e.message ?? 'Unable to create a held plan.'); }
        finally { setBusy(false); }
    };
    const bulkCreate = async () => {
        setBusy(true); setError('');
        try {
            const requestedTime = new Date(runAt);
            if (!runAt || Number.isNaN(requestedTime.valueOf())) throw new Error('Choose a valid date and time.');
            const selected = approved.filter(request => selectedApprovals.includes(request.id));
            if (!selected.length) throw new Error('Select at least one approved target.');
            await axios.post(`${base}/publication-intents/bulk`, { plans: selected.map(request => ({
                item_uuid: item.uuid, target_uuid: request.target_uuid,
                approval_request_id: request.id,
                occurrence_key: (() => {
                    const identity = `${request.target_uuid}:${request.id}:${requestedTime.toISOString()}`;
                    const key = pendingKeys.current.get(identity) ?? window.crypto.randomUUID();
                    pendingKeys.current.set(identity, key);
                    return key;
                })(), run_at: requestedTime.toISOString(),
            })) });
            selected.forEach(request => pendingKeys.current.delete(`${request.target_uuid}:${request.id}:${requestedTime.toISOString()}`));
            setSelectedApprovals([]);
            await load();
        } catch (e) { setError(e?.response?.data?.message ?? e.message ?? 'Unable to create bulk plans.'); }
        finally { setBusy(false); }
    };
    const cancel = async (id) => {
        setBusy(true); setError('');
        try { await axios.post(`${base}/publication-intents/${id}/cancel`); await load(); }
        catch (e) { setError(e?.response?.data?.message ?? 'Unable to cancel the plan.'); }
        finally { setBusy(false); }
    };
    const bulkCancel = async () => {
        setBusy(true); setError('');
        try {
            await axios.post(`${base}/publication-intents/bulk-cancel`, { ids: selectedJobs });
            setSelectedJobs([]); await load();
        } catch (e) { setError(e?.response?.data?.message ?? 'Unable to cancel selected plans.'); }
        finally { setBusy(false); }
    };
    const reschedule = async (row) => {
        setBusy(true); setError('');
        try {
            const input = rescheduleTimes[row.id];
            const date = new Date(input);
            if (!input || Number.isNaN(date.getTime())) throw new Error('Select a valid new date/time.');
            await axios.post(`${base}/publication-intents/${row.id}/reschedule`, {
                run_at: date.toISOString(), expected_version: row.version,
            });
            setRescheduleTimes(current => ({ ...current, [row.id]: '' }));
            await load();
        } catch (e) { setError(e?.response?.data?.message ?? e.message ?? 'Unable to reschedule the plan.'); }
        finally { setBusy(false); }
    };
    const arm = async (row) => {
        if (!window.confirm('Arm this approved plan for LIVE provider delivery when due? This may create an external post.')) return;
        setBusy(true); setError('');
        try {
            await axios.post(`${base}/publication-intents/${row.id}/arm`, {
                expected_version: row.version, acknowledge_live_delivery: true,
            });
            await load();
        } catch (e) { setError(e?.response?.data?.message ?? 'Publication cannot be armed. Check provider approval and kill switches.'); }
        finally { setBusy(false); }
    };
    const inspectHeld = async () => {
        const ids = rows.filter(row => row.state === 'AWAITING_USER'
            && approved.some(a => a.id === row.approval_request_id)).map(row => row.id).slice(0, 50);
        if (!ids.length) { setError('No held plans to inspect.'); return; }
        setBusy(true); setError('');
        try {
            const result = await axios.post(`${base}/publication-intents/inspect`, { ids });
            setPreflight(result.data?.data ?? null);
        } catch (e) { setError(e?.response?.data?.message ?? 'Unable to inspect held plans.'); }
        finally { setBusy(false); }
    };
    const inspect = async (id) => {
        setError('');
        try {
            const result = await axios.get(`${base}/publication-operations/${id}`);
            setHistory(result.data?.data ?? null);
            setInspectedState((operations?.jobs ?? []).find(job => job.id === id)?.state ?? null);
        } catch { setError('Unable to load publication history.'); }
    };
    return <section className="rounded-xl border border-border bg-card p-5 shadow-card" aria-label="Held publication plans">
        <h2 className="text-lg font-semibold">Publication planning desk</h2>
        <p className="mt-2 text-sm text-muted-foreground">Plans default to manual hold. Verified production text targets can be explicitly armed by managers only after all release switches and account capabilities are approved. Publishing is off by default.</p>
        <label className="mt-4 block text-sm">Planned local date and time
            <input type="datetime-local" className="mt-1 w-full rounded-lg border border-input bg-background px-3 py-2" value={runAt} onChange={e => setRunAt(e.target.value)} />
        </label>
        {approved.map(request => <div className="mt-3 flex items-center justify-between gap-2" key={request.id}>
            <span className="text-sm">Approved review #{request.id} · v{request.version_number}</span>
            <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => create(request)}>Hold plan</Button>
        </div>)}
        {approved.length > 1 && <div className="mt-4 rounded-lg border border-border p-3" aria-label="Bulk held planning">
            <h3 className="font-semibold">Bulk hold approved targets</h3>
            <p className="mt-1 text-xs text-muted-foreground">All selected plans are created atomically, or none are created.</p>
            {approved.map(request => <label key={request.id} className="mt-2 flex items-center gap-2 text-sm">
                <input type="checkbox" checked={selectedApprovals.includes(request.id)} onChange={e => setSelectedApprovals(current => e.target.checked ? [...current, request.id] : current.filter(id => id !== request.id))} />
                Approval #{request.id} · target {request.target_uuid}
            </label>)}
            <Button className="mt-3" size="sm" type="button" disabled={busy || selectedApprovals.length === 0 || selectedApprovals.length > 20} onClick={bulkCreate}>Hold selected plans</Button>
        </div>}
        {!approved.length && <p className="mt-3 text-sm text-muted-foreground">An approved current content target is needed before planning.</p>}
        {!!error && <p role="alert" className="mt-3 text-sm text-destructive">{error}</p>}
        <div className="mt-5 rounded-lg border border-border p-3" aria-label="Held plan preflight">
            <div className="flex items-center justify-between gap-2">
                <div>
                    <h3 className="font-semibold">Approval and media preflight</h3>
                    <p className="text-xs text-muted-foreground">Read-only inspection of up to 50 held plans. Never grants publishing permission.</p>
                </div>
                <Button type="button" size="sm" variant="outline" onClick={inspectHeld} disabled={busy}>Inspect held plans</Button>
            </div>
            {preflight && <div aria-live="polite" className="mt-3 text-sm">
                <p>Inspected: {preflight.summary?.inspected ?? 0} · Hold eligible: {preflight.summary?.hold_eligible ?? 0} · Needs review: {preflight.summary?.requires_review ?? 0}</p>
                <ul className="mt-2 space-y-2">{(preflight.items ?? []).map(result => <li className="rounded border border-border p-2" key={result.job_id}>
                    <strong>Plan #{result.job_id}: {result.assessment}</strong>
                    <p className="mt-1 text-xs text-muted-foreground">{(result.hard_blockers ?? []).length ? `Action required: ${result.hard_blockers.join(', ')}` : 'No hard approval/media blockers detected. Publishing remains disabled.'}</p>
                </li>)}</ul>
            </div>}
        </div>
        <h3 className="mt-6 font-semibold">Held plans</h3>
        <p className="mt-1 text-xs text-muted-foreground">Rescheduling never dispatches a post. Concurrent changes are rejected using a plan version check.</p>
        <ul className="mt-2 space-y-2">{rows.filter(row => approved.some(a => a.id === row.approval_request_id)).map(row =>
            <li key={row.id} className="rounded-lg border border-border p-3 text-sm">
                <div className="flex items-center justify-between gap-2">
                    <label className="flex items-center gap-2">
                        {['AWAITING_USER','SCHEDULED'].includes(row.state) && <input aria-label={`Select plan ${row.id}`} type="checkbox" checked={selectedJobs.includes(row.id)} onChange={e => setSelectedJobs(current => e.target.checked ? [...current, row.id] : current.filter(id => id !== row.id))} />}
                        <span>#{row.id} · {row.state} · {new Date(row.run_at).toLocaleString()} · rev {row.version}</span>
                    </label>
                    {['AWAITING_USER','SCHEDULED'].includes(row.state) && <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => cancel(row.id)}>Cancel before send</Button>}
                    {row.state === 'AWAITING_USER' && <Button type="button" size="sm" variant="outline" disabled={busy || !operations?.execution?.enabled} onClick={() => arm(row)}>Arm verified live plan</Button>}
                </div>
                {row.state === 'AWAITING_USER' && <div className="mt-2 flex flex-wrap items-end gap-2">
                    <label>New local date/time <input type="datetime-local" className="block rounded border border-input bg-background px-2 py-1" value={rescheduleTimes[row.id] ?? ''} onChange={e => setRescheduleTimes(current => ({ ...current, [row.id]: e.target.value }))} /></label>
                    <Button type="button" size="sm" variant="outline" disabled={busy || !rescheduleTimes[row.id]} onClick={() => reschedule(row)}>Reschedule hold</Button>
                </div>}
            </li>)}
        </ul>
        {selectedJobs.length > 0 && <Button type="button" className="mt-3" size="sm" variant="destructive" disabled={busy || selectedJobs.length > 50} onClick={bulkCancel}>Cancel {selectedJobs.length} selected plans atomically</Button>}
        <div className="mt-6 rounded-lg border border-border p-4" aria-label="Publication operations overview">
            <h3 className="font-semibold">Publication operations — receipts, attempts and recovery</h3>
            <p className="mt-1 text-xs text-muted-foreground">Live delivery requires explicit deployment release and manager arming. UNCERTAIN records require reconciliation and are never automatically retried.</p>
            {operations && <>
                <div className="mt-3 rounded border border-border p-2 text-xs" role="status">
                    Live execution: {operations.execution?.enabled ? 'RELEASED' : 'DISABLED (default)'} · Eligible: {operations.execution?.supported_live_text_providers?.join(', ') ?? 'none'} · Automatic retries: OFF · Media delivery: OFF
                </div>
                <p className="mt-2 text-xs text-muted-foreground">Confirmed provider posts in this brand: {operations.confirmed_published_posts ?? 0}. This count is based on persisted remote receipts, not simulation results.</p>
                <div className="mt-3 flex flex-wrap gap-3 text-sm">{Object.entries(operations.counts ?? {}).map(([status,count]) =>
                    <span className="rounded-md bg-muted px-2 py-1" key={status}>{status}: {count}</span>)}</div>
                <ul className="mt-3 space-y-2">{(operations.jobs ?? []).filter(job => approved.some(a => a.id === job.approval_request_id)).map(job =>
                    <li key={job.id} className="flex items-center justify-between gap-3 border-b border-border pb-2 text-sm">
                        <span>#{job.id} · {job.state}{job.requires_reconciliation ? ' · Manual reconciliation required' : ''}</span>
                        <Button size="sm" type="button" variant="outline" onClick={() => inspect(job.id)}>History</Button>
                    </li>)}</ul>
            </>}
            {history && <div className="mt-4 rounded-lg bg-muted p-3 text-sm" aria-live="polite">
                <p className="font-semibold">Job #{history.job_id} history</p>
                {(history.events ?? []).map(event => <p className="mt-1" key={event.id}>{event.event_type} · {event.from_state ?? '—'} → {event.to_state}</p>)}
                {(history.attempts ?? []).map(attempt => <p className="mt-1" key={attempt.id}>Attempt {attempt.attempt_number}: {attempt.result}</p>)}
                {history.published_post && <p className="mt-2 rounded border border-border p-2 text-xs">Confirmed post: {history.published_post.provider} · {history.published_post.external_post_id} · {history.published_post.remote_status}</p>}
                <p className="mt-2 text-xs text-muted-foreground">{history.note}</p>
                {inspectedState === 'UNCERTAIN' && <PublicationReconciliationPanel base={base} jobId={history.job_id} />}
            </div>}
        </div>
        <MediaPublicationPanel base={base} jobs={rows} />
        <RecurringCampaignPanel base={base} approvals={approvals} onHeld={load} />
        <PublicationCalendarPanel base={base} />
        <PublicationSimulationPanel base={base} />
    </section>;
}
