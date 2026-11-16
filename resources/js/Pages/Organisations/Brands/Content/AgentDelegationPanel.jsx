import { useCallback, useEffect, useState } from 'react';
import axios from 'axios';
import { Button } from '@/Components/ui/button';

/** Human-controlled delegation. Tokens are never stored in browser storage or sent to the AI model. */
/** Human edits use optimistic hash fencing; the AI delegation cannot revise its own output. */
function HumanResourceEditor({ run, busy, onSave, onPromote }) {
    const [text, setText] = useState(run.resource_text ?? '');
    const [note, setNote] = useState('');
    useEffect(() => { setText(run.resource_text ?? ''); setNote(''); }, [run.resource_hash, run.resource_text]);
    const changed = text.trim() !== (run.resource_text ?? '');
    return <div className="mt-2 space-y-2">
        <details><summary className="cursor-pointer text-xs">Generated resource and human corrections</summary>
            <label className="mt-2 block text-xs">Editable editorial copy<textarea rows={8} maxLength={6000} value={text} onChange={event => setText(event.target.value)} className="mt-1 w-full rounded border border-input bg-background p-2 text-sm" /></label>
            {changed && <label className="block text-xs">Reason for editorial revision<input value={note} onChange={event => setNote(event.target.value)} required minLength={5} maxLength={250} placeholder="Correcting an unsupported claim" className="mt-1 w-full rounded border border-input bg-background p-2 text-sm" /></label>}
            {changed && <Button type="button" size="sm" variant="outline" disabled={busy || text.trim().length < 20 || note.trim().length < 5} onClick={() => onSave(run, text, note)}>Save reviewed corrections</Button>}
            <p className="mt-1 text-xs text-muted-foreground">Human revisions: {run.resource_revision_count ?? 0}/12 · every revision preserves an event with old/new SHA-256</p>
        </details>
        <Button size="sm" type="button" disabled={busy || changed} onClick={() => onPromote(run)}>Convert to reviewable draft</Button>
        {changed && <p className="text-xs text-amber-700">Save your corrections before converting this resource to a draft.</p>}
    </div>;
}

export default function AgentDelegationPanel({ organisationUuid, brandUuid, canManage = false, accounts = [] }) {
    const endpoint = `/organisations/${organisationUuid}/brands/${brandUuid}/agent/delegations`;
    const [enabled, setEnabled] = useState(false);
    const [delegatedAvailable, setDelegatedAvailable] = useState(false);
    const [grants, setGrants] = useState([]);
    const [orders, setOrders] = useState([]);
    const [runs, setRuns] = useState([]);
    const [autonomyAvailable, setAutonomyAvailable] = useState(false);
    const [researchTitle, setResearchTitle] = useState('');
    const [researchObjective, setResearchObjective] = useState('');
    const [resourceKind, setResourceKind] = useState('SOCIAL_COPY');
    const [researchGrantId, setResearchGrantId] = useState('');
    const [name, setName] = useState('My marketing AI agent');
    const [mode, setMode] = useState('REVIEW_REQUIRED');
    const [days, setDays] = useState(1);
    const [dailyActions, setDailyActions] = useState(20);
    const [dailyArms, setDailyArms] = useState(1);
    const [selected, setSelected] = useState([]);
    const [token, setToken] = useState('');
    const [selectedOrder, setSelectedOrder] = useState(null);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const refresh = useCallback(async () => {
        try {
            const { data } = await axios.get(endpoint);
            setEnabled(Boolean(data.data?.enabled));
            setDelegatedAvailable(Boolean(data.data?.delegated_publishing_available));
            setGrants(data.data?.grants ?? []);
            setOrders(data.data?.work_orders ?? []);
            setRuns(data.data?.autonomous_runs ?? []);
            setAutonomyAvailable(Boolean(data.data?.autonomous_research_available));
        } catch { setMessage('Unable to load agent delegations for this brand.'); }
    }, [endpoint]);
    useEffect(() => { if (canManage) refresh(); }, [canManage, refresh]);
    useEffect(() => () => setToken(''), []);
    if (!canManage) return null;
    const issue = async e => {
        e.preventDefault(); setBusy(true); setToken(''); setMessage('');
        try {
            const { data } = await axios.post(endpoint, {
                name, mode, expires_in_days: Number(days), daily_action_limit: Number(dailyActions),
                daily_arm_limit: mode === 'DELEGATED_APPROVED_PUBLISH' ? Number(dailyArms) : 0,
                account_uuids: selected,
            });
            setToken(data.data?.plain_text_token ?? '');
            setMessage('Delegation created. The secret is shown once; give it only to a trusted connector in a secure connection form.');
            await refresh();
        } catch (e) { setMessage(e.response?.data?.message ?? 'Delegation denied. Verify owner role, scopes, and release settings.'); }
        finally { setBusy(false); }
    };
    const inspectOrder = async uuid => {
        try {
            const { data } = await axios.get(`/organisations/${organisationUuid}/brands/${brandUuid}/agent/work-orders/${uuid}`);
            setSelectedOrder(data.data ?? null);
        } catch { setMessage('Cannot load this work order evidence.'); }
    };
    const startResearch = async e => {
        e.preventDefault(); setBusy(true); setMessage('');
        try {
            const key = `research-${crypto.randomUUID()}`;
            await axios.post(`/organisations/${organisationUuid}/brands/${brandUuid}/agent/autonomous-runs`, {
                delegation_id: Number(researchGrantId), run_key: key, idempotency_key: key,
                title: researchTitle.trim(), objective: researchObjective.trim(), resource_kind: resourceKind,
            });
            setResearchTitle(''); setResearchObjective('');
            setMessage('Research queued. Refresh to inspect citations and the generated resource; both stages require the opted-in worker.');
            await refresh();
        } catch (e) { setMessage(e.response?.data?.message ?? 'Research could not be queued. Check access and release flags.'); }
        finally { setBusy(false); }
    };
    const cancelResearch = async run => {
        setBusy(true); setMessage('');
        try {
            await axios.post(`/organisations/${organisationUuid}/brands/${brandUuid}/agent/autonomous-runs/${run.uuid}/cancel`, {
                delegation_id: run.delegation_id, idempotency_key: `cancel-${run.uuid}`,
            });
            setMessage('Run cancellation recorded. In-flight provider requests cannot be interrupted.');
            await refresh();
        } catch (e) { setMessage(e.response?.data?.message ?? 'Unable to cancel this run.'); }
        finally { setBusy(false); }
    };
    const promoteResource = async run => {
        setBusy(true); setMessage('');
        try {
            await axios.post(`/organisations/${organisationUuid}/brands/${brandUuid}/agent/autonomous-runs/${run.uuid}/promote`, {
                delegation_id: run.delegation_id, idempotency_key: `promote-${run.uuid}`,
            });
            setMessage('Generated resource converted into an immutable draft. Independent human approval is still required.');
            await refresh();
        } catch (e) { setMessage(e.response?.data?.message ?? 'Resource promotion denied.'); }
        finally { setBusy(false); }
    };
    const reviseResource = async (run, correctedText, note) => {
        setBusy(true); setMessage('');
        try {
            await axios.post(`/organisations/${organisationUuid}/brands/${brandUuid}/agent/autonomous-runs/${run.uuid}/revise`, {
                delegation_id: run.delegation_id, expected_resource_hash: run.resource_hash,
                resource_text: correctedText.trim(), review_note: note.trim(),
            });
            setMessage('Human editorial corrections saved with SHA-256 revision evidence. The draft still requires independent approval.');
            await refresh();
        } catch (error) {
            setMessage(error.response?.status === 409 ? 'This resource was edited elsewhere; reload before applying changes.' : 'Unable to save reviewed resource. Check permission and editorial limits.');
        } finally { setBusy(false); }
    };
    const revoke = async id => {
        setBusy(true); setToken(''); setMessage('');
        try { await axios.delete(`${endpoint}/${id}`); setMessage('Agent access revoked immediately.'); await refresh(); }
        catch { setMessage('Unable to revoke delegation.'); }
        finally { setBusy(false); }
    };
    return <section className="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-card" aria-label="AI agent orchestration permissions">
        <h2 className="text-lg font-semibold">AI Agent · Research → Draft → Review → Publish</h2>
        <p className="mt-2 text-sm text-muted-foreground">Connect a trusted AI agent to assemble campaigns in this brand. The AI can capture cited research, create drafts and media links, prepare platform targets, request review and plan approved posts. An AI agent cannot approve content. Human-granted publication arming is available only with a separate owner-issued permission and all live safety gates.</p>
        <p className="mt-2 text-xs text-muted-foreground">Access: {enabled ? 'Enabled' : 'Disabled (default)'} · Delegated publishing: {delegatedAvailable ? 'Available with owner permission' : 'Disabled (default)'}</p>
        {enabled && <form onSubmit={issue} className="mt-4 grid gap-3 sm:grid-cols-2">
            <label className="text-sm">Delegation name<input className="mt-1 block w-full rounded border border-input bg-background p-2" value={name} onChange={e=>setName(e.target.value)} minLength={3} maxLength={100} required /></label>
            <label className="text-sm">Permission mode<select className="mt-1 block w-full rounded border border-input bg-background p-2" value={mode} onChange={e=>setMode(e.target.value)}>
                <option value="REVIEW_REQUIRED">Human review required</option>
                {delegatedAvailable && <option value="DELEGATED_APPROVED_PUBLISH">Delegated: arm already-approved posts</option>}
            </select></label>
            <label className="text-sm">Expires after (days, max 7)<input type="number" min={1} max={7} required className="mt-1 block w-full rounded border border-input bg-background p-2" value={days} onChange={e=>setDays(e.target.value)} /></label>
            <label className="text-sm">Maximum actions per day<input type="number" min={1} max={100} required className="mt-1 block w-full rounded border border-input bg-background p-2" value={dailyActions} onChange={e=>setDailyActions(e.target.value)} /></label>
            {mode==='DELEGATED_APPROVED_PUBLISH' && <label className="text-sm">Maximum approved posts to arm per day<input type="number" min={1} max={10} required className="mt-1 block w-full rounded border border-input bg-background p-2" value={dailyArms} onChange={e=>setDailyArms(e.target.value)} /></label>}
            <fieldset className="sm:col-span-2"><legend className="text-sm font-medium">Allowlisted social accounts (no implicit access)</legend>
                <div className="mt-1 flex flex-wrap gap-3">{accounts.map(a=><label key={a.uuid} className="flex items-center gap-2 text-xs"><input type="checkbox" checked={selected.includes(a.uuid)} onChange={e=>setSelected(prev=>e.target.checked?[...prev,a.uuid]:prev.filter(x=>x!==a.uuid))} />{a.display_name} · {a.provider}</label>)}</div>
                {!accounts.length && <p className="mt-1 text-xs text-muted-foreground">Connect a social account before authorizing platform targeting. Research and drafts can still work without one.</p>}
            </fieldset>
            <div className="sm:col-span-2"><Button type="submit" disabled={busy}>{busy ? 'Creating…' : 'Create scoped agent delegation'}</Button></div>
        </form>}
        {token && <div role="status" className="mt-4 rounded border border-amber-500 p-3">
            <p className="font-medium text-sm">One-time token — never paste into an AI prompt or public chat</p>
            <textarea readOnly rows={2} className="mt-2 w-full rounded border border-input bg-background p-2 font-mono text-xs" aria-label="New agent access token" value={token} />
            <Button type="button" size="sm" variant="outline" onClick={()=>setToken('')}>Hide token</Button>
        </div>}
        {message && <p role="status" className="mt-3 text-xs text-muted-foreground">{message}</p>}
        <h3 className="mt-5 font-medium">Active and revoked delegations</h3>
        <ul className="mt-2 divide-y divide-border text-sm">{grants.map(g=><li key={g.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
            <span>{g.name} · {g.mode === 'REVIEW_REQUIRED'?'Review': 'Delegated'} · expires {g.expires_at} · {g.revoked_at?'Revoked':'Active or expired'} · {g.daily_action_limit} actions/day</span>
            {!g.revoked_at && <Button type="button" size="sm" variant="outline" disabled={busy} onClick={()=>revoke(g.id)}>Revoke</Button>}
        </li>)}</ul>
        <h3 className="mt-5 font-medium">Autonomous research and content resources</h3>
        <p className="mt-1 text-xs text-muted-foreground">Opt-in, source-attributed web research → generated editorial resource → normal draft → independent content approval. No automatic posting. Unknown or expired worker outcomes are quarantined for investigation, not replayed.</p>
        {autonomyAvailable && <form className="mt-3 grid gap-2 sm:grid-cols-2" onSubmit={startResearch}>
            <label className="text-xs">Agent delegation<select required value={researchGrantId} onChange={e=>setResearchGrantId(e.target.value)} className="mt-1 w-full rounded border border-input bg-background p-2">
                <option value="">Select active delegation</option>
                {grants.filter(g=>!g.revoked_at).map(g=><option key={g.id} value={g.id}>{g.name} · {g.mode}</option>)}
            </select></label>
            <label className="text-xs">Resource type<select value={resourceKind} onChange={e=>setResourceKind(e.target.value)} className="mt-1 w-full rounded border border-input bg-background p-2"><option value="SOCIAL_COPY">Social copy</option><option value="CAMPAIGN_BRIEF">Campaign brief</option></select></label>
            <label className="text-xs sm:col-span-2">Research topic<input required minLength={3} maxLength={180} value={researchTitle} onChange={e=>setResearchTitle(e.target.value)} className="mt-1 w-full rounded border border-input bg-background p-2" placeholder="Audience and positioning research" /></label>
            <label className="text-xs sm:col-span-2">Objective<textarea required minLength={5} maxLength={3000} value={researchObjective} onChange={e=>setResearchObjective(e.target.value)} rows={2} className="mt-1 w-full rounded border border-input bg-background p-2" placeholder="What should the research investigate?" /></label>
            <div className="sm:col-span-2"><Button type="submit" disabled={busy || !researchGrantId}>Queue research and resource</Button></div>
        </form>}
        {!autonomyAvailable && <p className="mt-2 text-xs text-muted-foreground">Autonomous execution is disabled by deployment policy.</p>}
        <ul className="mt-3 space-y-3">{runs.map(run=><li key={run.uuid} className="rounded border border-border p-3 text-sm">
            <div className="flex items-center justify-between gap-2"><strong>{run.title}</strong><span>{run.status}</span></div>
            <p className="mt-1 text-xs text-muted-foreground">{run.resource_kind} · {run.sources?.length ?? 0} unverified cited URLs · source/hash integrity recorded</p>
            {run.failure_code && <p className="mt-1 text-xs text-amber-600">{run.failure_code} · needs operator reconciliation, no automatic retry</p>}
            {run.findings && <details className="mt-2"><summary className="cursor-pointer">Research findings & citations</summary>
                <p className="mt-2 whitespace-pre-wrap text-xs">{run.findings}</p>
                <ul>{(run.sources??[]).map((s,i)=><li key={i} className="text-xs"><a href={s.url} target="_blank" rel="noopener noreferrer" className="text-primary underline">{s.title || s.url}</a> · not independently verified</li>)}</ul>
            </details>}
            {run.status === 'READY_FOR_REVIEW' && <HumanResourceEditor run={run} busy={busy} onSave={reviseResource} onPromote={promoteResource} />}
            {run.resource_text && run.status !== 'READY_FOR_REVIEW' && <details className="mt-2"><summary className="cursor-pointer">Generated resource (human review required)</summary><p className="mt-2 whitespace-pre-wrap text-xs">{run.resource_text}</p></details>}
            {['QUEUED_RESEARCH','QUEUED_RESOURCE','READY_FOR_REVIEW'].includes(run.status) && <Button size="sm" variant="outline" type="button" className="mt-2 ml-2" disabled={busy} onClick={()=>cancelResearch(run)}>Cancel unstarted work</Button>}
            {run.content_uuid && <p className="mt-2 text-xs">Draft: {run.content_uuid} · pending regular approval</p>}
        </li>)}</ul>
        <h3 className="mt-5 font-medium">Recent agent work orders</h3>
        <ul className="mt-2 divide-y divide-border text-sm">{orders.map(o=><li key={o.uuid} className="py-2"><strong>{o.title}</strong> · {o.status} · {o.sources_count} unverified research sources {o.draft_content_uuid ? `· draft ${o.draft_content_uuid}` : ''} <Button type="button" size="sm" variant="outline" onClick={()=>inspectOrder(o.uuid)}>Review evidence</Button></li>)}</ul>
        {!orders.length && <p className="mt-2 text-sm text-muted-foreground">No agent work orders yet.</p>}
        {selectedOrder && <aside className="mt-3 rounded border border-border p-3" aria-label="Agent research and action evidence">
            <h4 className="font-semibold">{selectedOrder.title} — attributed AI research</h4>
            <p className="mt-1 text-xs text-amber-700 dark:text-amber-400">Sources and findings were supplied by an AI agent and have NOT been independently verified. Review before relying on claims or approving publication.</p>
            <p className="mt-2 text-sm">Objective: {selectedOrder.objective}</p>
            <p className="mt-2 whitespace-pre-wrap text-sm">{selectedOrder.findings}</p>
            <ul className="mt-2 space-y-2">{(selectedOrder.sources ?? []).map((src, i)=><li key={i} className="text-xs">
                <a className="text-primary underline" href={src.url} target="_blank" rel="noopener noreferrer">{src.title || src.url}</a> — {src.note || 'No notes'} · unverified
            </li>)}</ul>
            <p className="mt-2 break-all font-mono text-xs text-muted-foreground">Research digest: {selectedOrder.research_hash}</p>
            <ol className="mt-2 text-xs text-muted-foreground">{(selectedOrder.events ?? []).map((event,i)=><li key={i}>{event.created_at} · {event.action}</li>)}</ol>
            <Button type="button" variant="outline" size="sm" onClick={()=>setSelectedOrder(null)}>Close evidence</Button>
        </aside>}
        <div className="mt-4 space-y-2 text-xs text-muted-foreground">
            <p>Trusted-client connection: <code>/mcp/planner-agent</code> accepts a pre-issued Sanctum token. Native OAuth MCP: <code>/mcp/planner-agent-oauth</code> uses interactive PKCE login and explicit delegation consent, after OAuth is enabled on the server.</p>
            <p>An OAuth connection reuses an existing delegation; it does not generate new publishing authority. <a href="/oauth/links" className="text-primary underline">Manage connected OAuth clients</a>. Existing global publication and review safeguards still apply.</p>
        </div>
    </section>;
}
