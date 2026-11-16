import { useEffect, useState } from 'react';
import axios from 'axios';
import { Button } from '@/Components/ui/button';

/** Read-only remote status: staging media is never a permission to publish. */
export default function MediaPublicationPanel({ base, jobs = [] }) {
    const [preparations, setPreparations] = useState([]);
    const [history, setHistory] = useState(null);
    const [runs, setRuns] = useState([]);
    const [runHistory, setRunHistory] = useState(null);
    const [youtubeTransfers, setYoutubeTransfers] = useState([]);
    const [youtubeRelease, setYoutubeRelease] = useState(null);
    const [youtubeHistory, setYoutubeHistory] = useState(null);
    const [creatorOptions, setCreatorOptions] = useState(null);
    const [tiktokPrivacy, setTiktokPrivacy] = useState('');
    const [tiktokSettings, setTiktokSettings] = useState({ disable_comment: true, disable_duet: true, disable_stitch: true, is_aigc: false, explicit_consent: false });
    const [release, setRelease] = useState([]);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const path = `${base}/media-publication-preparations`;
    const runsPath = `${base}/media-publication-runs`;
    const youtubePath = `${base}/youtube-transfers`;
    const load = async () => {
        try {
            const [response, gates, staged, ytTransfers, ytRelease] = await Promise.all([
                axios.get(path), axios.get(`${base}/media-publication-release`), axios.get(runsPath),
                axios.get(youtubePath), axios.get(`${youtubePath}/release`),
            ]);
            setPreparations(response.data?.data ?? []);
            setRelease(gates.data?.data ?? []);
            setRuns(staged.data?.data ?? []);
            setYoutubeTransfers(ytTransfers.data?.data ?? []);
            setYoutubeRelease(ytRelease.data?.data ?? null);
        }
        catch { setError('Unable to retrieve media preparations.'); }
    };
    useEffect(() => { load(); }, [path]);
    const prepare = async jobId => {
        setBusy(true); setError('');
        try { await axios.post(path, { publication_job_id: jobId }); await load(); }
        catch (e) { setError(e?.response?.data?.message ?? 'Media cannot be prepared. Check current approval and Storage media.'); }
        finally { setBusy(false); }
    };
    const abort = async prep => {
        setBusy(true); setError('');
        try { await axios.post(`${path}/${prep.id}/abort`, { expected_version: prep.version }); await load(); }
        catch (e) { setError(e?.response?.data?.message ?? 'Media preparation was changed or cannot be aborted.'); }
        finally { setBusy(false); }
    };
    const view = async id => {
        try { const response = await axios.get(`${path}/${id}`); setHistory(response.data?.data ?? null); }
        catch { setError('Unable to load media preparation history.'); }
    };
    const createRun = async prep => {
        setBusy(true); setError('');
        try { await axios.post(runsPath, { preparation_id: prep.id }); await load(); }
        catch (e) { setError(e?.response?.data?.message ?? 'Unable to create a durable media run.'); }
        finally { setBusy(false); }
    };
    const armRun = async run => {
        setBusy(true); setError('');
        try { await axios.post(`${runsPath}/${run.id}/arm`, { expected_version: run.version }); await load(); }
        catch (e) { setError(e?.response?.data?.message ?? 'Media release prerequisites are not satisfied.'); }
        finally { setBusy(false); }
    };
    const cancelRun = async run => {
        setBusy(true); setError('');
        try { await axios.post(`${runsPath}/${run.id}/cancel`, { expected_version: run.version }); await load(); }
        catch (e) { setError(e?.response?.data?.message ?? 'A leased or attempted run cannot be cancelled.'); }
        finally { setBusy(false); }
    };
    const inspectRun = async id => {
        try { const reply = await axios.get(`${runsPath}/${id}`); setRunHistory(reply.data?.data ?? null); }
        catch { setError('Unable to inspect media stage history.'); }
    };
    const createYouTubeTransfer = async run => {
        setBusy(true); setError('');
        try { await axios.post(youtubePath, { media_run_id: run.id }); await load(); }
        catch (e) { setError(e?.response?.data?.message ?? 'Unable to register the YouTube resumable transfer.'); }
        finally { setBusy(false); }
    };
    const armYouTubeTransfer = async transfer => {
        setBusy(true); setError('');
        try { await axios.post(`${youtubePath}/${transfer.id}/arm`, { version: transfer.version }); await load(); }
        catch (e) { setError(e?.response?.data?.message ?? 'YouTube release or trusted Storage reader is unavailable.'); }
        finally { setBusy(false); }
    };
    const inspectYouTubeTransfer = async id => {
        try { const reply = await axios.get(`${youtubePath}/${id}`); setYoutubeHistory(reply.data?.data ?? null); }
        catch { setError('Unable to retrieve transfer checkpoint history.'); }
    };
    const inspectTikTok = async run => {
        setBusy(true); setError('');
        try {
            const response = await axios.get(`${runsPath}/${run.id}/tiktok-creator-options`);
            const data = response.data?.data;
            if (!data) throw new Error('missing options');
            setCreatorOptions(data);
            setTiktokPrivacy(data.privacy_levels?.[0] ?? '');
            setTiktokSettings(settings => ({ ...settings,
                disable_comment: !!data.comment_disabled, disable_duet: !!data.duet_disabled,
                disable_stitch: !!data.stitch_disabled, explicit_consent: false }));
        }
        catch { setCreatorOptions(null); setError('TikTok creator options could not be verified. Do not guess privacy levels.'); }
        finally { setBusy(false); }
    };
    const submitTikTokConsent = async () => {
        if (!creatorOptions || !tiktokSettings.explicit_consent) return;
        setBusy(true); setError('');
        try {
            await axios.post(`${runsPath}/${creatorOptions.run_id}/tiktok-consent`, {
                privacy_level: tiktokPrivacy, ...tiktokSettings,
            });
            setCreatorOptions(null);
            setTiktokSettings(current => ({ ...current, explicit_consent: false }));
            await load();
        }
        catch (e) { setError(e?.response?.data?.message ?? 'TikTok consent was not accepted. Refresh creator options.'); }
        finally { setBusy(false); }
    };
    const held = jobs.filter(job => job.state === 'AWAITING_USER');
    return <section aria-label="Media publishing preparations" className="mt-6 rounded-xl border border-border p-4">
        <h3 className="font-semibold">Instagram, TikTok & YouTube media preparation</h3>
        <p className="mt-1 text-xs text-muted-foreground">Requires current approval and canonical Storage evidence. Single-media protocols only. Preparation creates no remote request. Staged runs preserve lease, attempt and receipt evidence. All live media release switches remain OFF by default.</p>
        <div className="mt-3 grid gap-2 sm:grid-cols-3" aria-label="Provider media release prerequisites">
            {release.map(result => <div className="rounded border border-border p-2 text-xs" key={result.provider}>
                <p className="font-semibold capitalize">{result.provider} · blocked</p>
                <p className="mt-1 text-muted-foreground">{(result.release_blockers ?? []).join(', ')}</p>
            </div>)}
        </div>
        <div className="mt-3 flex flex-wrap gap-2">
            {held.map(job => <Button key={job.id} type="button" size="sm" variant="outline" disabled={busy}
                onClick={() => prepare(job.id)}>Prepare held plan #{job.id}</Button>)}
        </div>
        {held.length === 0 && <p className="mt-2 text-xs text-muted-foreground">Create a held plan for an approved media target first.</p>}
        {!!error && <p className="mt-3 text-sm text-destructive" role="alert">{error}</p>}
        <ul className="mt-4 space-y-2">
            {preparations.map(prep => <li key={prep.id} className="rounded border border-border p-3 text-sm">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span>Plan #{prep.publication_job_id} · {prep.provider} · {prep.protocol} · {prep.state}</span>
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => view(prep.id)}>Review stages</Button>
                        {prep.state === 'PREPARED' && <Button type="button" variant="outline" size="sm" disabled={busy} onClick={() => createRun(prep)}>Create stage run</Button>}
                        {prep.state === 'PREPARED' && <Button type="button" variant="outline" size="sm" disabled={busy} onClick={() => abort(prep)}>Abort preparation</Button>}
                    </div>
                </div>
                <p className="mt-1 text-xs text-muted-foreground">Blocker: {prep.blocker_code || 'None'} · Dispatch: disabled · Version {prep.version}</p>
            </li>)}
        </ul>
        <div className="mt-4 border-t border-border pt-3" aria-label="Durable media stage runs">
            <h4 className="font-medium text-sm">Media stage execution ledger</h4>
            <p className="mt-1 text-xs text-muted-foreground">Stage runs do not prove publication. Instagram requires verified app review, Storage distribution, and separately enabled execution. TikTok requires consent. YouTube's resumable stream is a separate, disabled-by-default transfer desk and requires a verified Storage adapter. Uncertain sends are never retried automatically.</p>
            <ul className="mt-2 space-y-2">
                {runs.map(run => <li key={run.id} className="rounded border border-border p-3 text-xs">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <span>Run #{run.id} · {run.provider} · {run.state} · {run.stage}</span>
                        <div className="flex gap-2">
                            <Button type="button" size="sm" variant="outline" onClick={() => inspectRun(run.id)}>Stage evidence</Button>
                            {run.provider !== 'youtube' && run.state === 'HELD' && <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => armRun(run)}>Arm (gated)</Button>}
                            {['HELD','ARMED'].includes(run.state) && <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => cancelRun(run)}>Cancel run</Button>}
                            {run.provider === 'youtube' && run.state === 'HELD' && <Button type="button" size="sm" variant="outline" disabled={busy}
                                onClick={() => createYouTubeTransfer(run)}>Prepare YouTube transfer</Button>}
                            {run.provider === 'tiktok' && run.state === 'HELD'  && <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => inspectTikTok(run)}>Verify creator privacy</Button>}
                        </div>
                    </div>
                    <p className="mt-1 text-muted-foreground">Sequence {run.stage_sequence} · Polls {run.poll_count}/12 · {run.blocker_code ?? 'No recorded blocker'} · Version {run.version}</p>
                </li>)}
            </ul>
            {runs.length === 0 && <p className="mt-2 text-xs text-muted-foreground">Create a run from a prepared media plan to inspect its execution lifecycle.</p>}
            <div className="mt-4 rounded border border-border p-3 text-xs" aria-label="YouTube resumable upload checkpoints">
                <h5 className="font-semibold">YouTube resumable upload ledger</h5>
                <p className="mt-1 text-muted-foreground">Uploads require a verified canonical Tetranyble Storage byte source. No URL, bearer token or video bytes are exposed. 308 range probes determine the only safe next byte offset.</p>
                {!!youtubeRelease && <p className="mt-1 text-muted-foreground">Release: {youtubeRelease.dispatch_enabled ? 'Operator-enabled (verify provider first)' : 'Disabled'} · {(youtubeRelease.release_blockers ?? []).join(', ')}</p>}
                <ul className="mt-2 space-y-2">
                    {youtubeTransfers.map(transfer => <li key={transfer.id} className="rounded border border-border p-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <span>Transfer #{transfer.id} · {transfer.state} · {transfer.next_action}</span>
                            <div className="flex gap-2">
                                <Button type="button" size="sm" variant="outline" onClick={() => inspectYouTubeTransfer(transfer.id)}>Inspect checkpoints</Button>
                                {transfer.state === 'HELD' && <Button type="button" size="sm" variant="outline" disabled={busy || !youtubeRelease?.storage_reader_verified}
                                    onClick={() => armYouTubeTransfer(transfer)}>Arm when verified</Button>}
                            </div>
                        </div>
                        <p className="mt-1 text-muted-foreground">Confirmed bytes: {transfer.committed_bytes.toLocaleString()} / {transfer.total_bytes.toLocaleString()} · Version {transfer.version} · {transfer.blocker_code ?? 'No recorded blocker'}</p>
                    </li>)}
                </ul>
                {youtubeTransfers.length === 0 && <p className="mt-2 text-muted-foreground">Create a YouTube run, then register its durable upload transfer.</p>}
                {youtubeHistory && <div className="mt-2 rounded bg-muted p-2" aria-live="polite">
                    <p className="font-semibold">Transfer #{youtubeHistory.id} — {youtubeHistory.state}</p>
                    {(youtubeHistory.attempts ?? []).map((attempt, i) => <p key={`${attempt.sequence}-${i}`}>{attempt.sequence}. {attempt.action} · offset {attempt.offset_bytes} · {attempt.state} ({attempt.outcome_code ?? 'pending'})</p>)}
                    {(youtubeHistory.events ?? []).map((e, i) => <p key={`${e.action}-${i}`}>{e.action} · {e.from_state ?? 'NEW'} → {e.to_state}</p>)}
                </div>}
            </div>
            {creatorOptions && <fieldset className="mt-3 rounded border border-border p-3 text-xs" aria-label="TikTok video consent">
                <legend className="font-semibold">TikTok — current creator privacy options</legend>
                <p className="mt-1 text-muted-foreground">Choose privacy for this exact approved video. Your choices are checked against TikTok again on submission; no publishing happens here.</p>
                <label className="mt-2 block">Privacy level
                    <select className="ml-2 rounded border border-border bg-background p-1" value={tiktokPrivacy} onChange={event => setTikTokPrivacy(event.target.value)}>
                        {(creatorOptions.privacy_levels ?? []).map(level => <option key={level} value={level}>{level.replaceAll('_',' ')}</option>)}
                    </select>
                </label>
                {['comment','duet','stitch'].map(kind => <label className="mt-2 flex items-center gap-2" key={kind}>
                    <input type="checkbox" checked={!!tiktokSettings[`disable_${kind}`]}
                        disabled={!!creatorOptions[`${kind}_disabled`]}
                        onChange={event => setTiktokSettings(current => ({ ...current, [`disable_${kind}`]: event.target.checked }))}/>
                    Disable {kind} {creatorOptions[`${kind}_disabled`] ? '(required by creator settings)' : ''}
                </label>)}
                <label className="mt-2 flex items-center gap-2"><input type="checkbox" checked={tiktokSettings.is_aigc}
                    onChange={event => setTiktokSettings(current => ({ ...current, is_aigc: event.target.checked }))}/>Label AI-generated content</label>
                <label className="mt-2 flex items-center gap-2"><input type="checkbox" checked={tiktokSettings.explicit_consent}
                    onChange={event => setTiktokSettings(current => ({ ...current, explicit_consent: event.target.checked }))}/>I explicitly consent to posting this approved video to the selected TikTok account under these settings.</label>
                <Button type="button" size="sm" className="mt-2" disabled={busy || !tiktokSettings.explicit_consent} onClick={submitTikTokConsent}>Record consent</Button>
            </fieldset>}
            {runHistory && <div className="mt-2 rounded bg-muted p-3 text-xs" aria-live="polite">
                <p className="font-semibold">Run #{runHistory.id} — {runHistory.state}</p>
                {(runHistory.attempts ?? []).map(a => <p key={a.stage_sequence}>{a.stage_sequence}. {a.operation} — {a.state} ({a.outcome_code ?? 'pending'})</p>)}
                {(runHistory.events ?? []).map((e, index) => <p key={`${e.action}-${index}`}>{e.action}: {e.from_state ?? 'NEW'} → {e.to_state}</p>)}
            </div>}
        </div>
        {history && <div className="mt-3 rounded bg-muted p-3 text-xs" aria-live="polite">
            <p className="font-semibold">Preparation #{history.id} — {history.provider}</p>
            <ol className="mt-2 list-inside list-decimal space-y-1">{(history.stages ?? []).map(stage => <li key={stage}>{stage.replaceAll('_', ' ')}</li>)}</ol>
            <p className="mt-2">No remote requests have been sent. Future media delivery requires provider permissions, verified media distribution, and a separately released worker.</p>
            {(history.events ?? []).map((event, index) => <p key={`${event.action}-${index}`} className="mt-1">{event.action}: {event.from_state ?? 'NEW'} → {event.to_state}</p>)}
        </div>}
    </section>;
}
