import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { Button } from '@/Components/ui/button';

/** Human-managed recurrence templates. Never auto-arm or auto-publish. */
export default function RecurringCampaignPanel({ base, approvals = [], onHeld }) {
    const [rows, setRows] = useState([]);
    const [selected, setSelected] = useState(null);
    const [preview, setPreview] = useState(null);
    const [projectionMode, setProjectionMode] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [form, setForm] = useState({ target_uuid: '', frequency: 'WEEKLY', interval: 1,
        weekdays: ['MO'], timezone: 'Africa/Lagos', start_local: '', max_occurrences: 20 });
    const [from, setFrom] = useState(new Date().toISOString().slice(0, 10));
    const approved = useMemo(() => approvals.filter(a => a.status === 'APPROVED' && a.target_uuid), [approvals]);
    const load = async () => {
        const response = await axios.get(`${base}/recurring-campaigns`);
        setRows(response.data?.data ?? []);
    };
    useEffect(() => { let active = true;
        axios.get(`${base}/recurring-campaigns`).then(({data}) => { if (active) setRows(data?.data ?? []); })
            .catch(() => { if (active) setError('Could not load recurring templates.'); });
        return () => { active = false; };
    }, [base]);
    const refreshPreview = async (schedule, first = from) => {
        const day = new Date(`${first}T00:00:00Z`);
        if (Number.isNaN(day.getTime())) throw new Error('Choose a valid UTC preview date.');
        const end = new Date(day.getTime() + 30 * 86400 * 1000);
        const {data} = await axios.get(`${base}/recurring-campaigns/${schedule.id}/preview`, {
            params: {from: day.toISOString(), to: end.toISOString()},
        });
        setSelected(schedule.id); setProjectionMode(false); setPreview(data?.data ?? null);
    };
    const execute = async fn => {
        setBusy(true); setError(''); setMessage('');
        try { await fn(); } catch (e) {
            setError(e?.response?.data?.message ?? e?.message ?? 'Recurring campaign action failed.');
        } finally { setBusy(false); }
    };
    const create = () => execute(async () => {
        if (!approved.some(a => a.target_uuid === form.target_uuid)) throw new Error('Select an approved content target.');
        if (!form.start_local) throw new Error('Select a start time in the schedule timezone.');
        const byday = form.frequency === 'WEEKLY' ? `;BYDAY=${form.weekdays.join(',')}` : '';
        if (form.frequency === 'WEEKLY' && form.weekdays.length === 0) throw new Error('Choose at least one weekday.');
        const rrule = `FREQ=${form.frequency};INTERVAL=${form.interval}${byday}`;
        const {data} = await axios.post(`${base}/recurring-campaigns`, {
            target_uuid: form.target_uuid, rrule, timezone: form.timezone,
            start_local: `${form.start_local.slice(0, 16).replace('T', ' ')}:00`, max_occurrences: Number(form.max_occurrences),
        });
        await load(); setPreview(null); setMessage(`Recurring template #${data?.data?.id} saved. No posts were scheduled or armed.`);
    });
    const changeState = (schedule, state) => execute(async () => {
        if (state === 'RETIRED' && !window.confirm('Retire this recurrence permanently? Existing held plans remain unchanged.')) return;
        await axios.post(`${base}/recurring-campaigns/${schedule.id}/state`, {
            state, expected_version: schedule.version,
        });
        await load(); setPreview(null);
        setMessage(`Template #${schedule.id} is now ${state}. Existing held plans are unchanged.`);
    });
    const showPreview = schedule => execute(async () => { await refreshPreview(schedule); });
    const showProjected = schedule => execute(async () => {
        const {data} = await axios.get(`${base}/recurring-campaigns/${schedule.id}/occurrences`);
        setSelected(schedule.id); setProjectionMode(true); setPreview(data?.data ?? null);
    });
    const hold = (schedule, slot) => execute(async () => {
        const approval = approved.find(a => a.target_uuid === schedule.target_uuid);
        if (!approval) throw new Error('A current approved target on this content item is required.');
        if (!window.confirm(`Create a held (NOT armed) plan for ${slot.local} ${schedule.timezone}?`)) return;
        await axios.post(`${base}/recurring-campaigns/${schedule.id}/hold`, {
            run_at: slot.run_at, approval_request_id: approval.id, expected_version: schedule.version,
        });
        setMessage(`Held ${slot.run_at}. Publishing has not been armed.`);
        if (projectionMode) await showProjected(schedule);
        await onHeld?.();
    });
    const current = rows.find(row => row.id === selected);
    return <section className="mt-5 rounded-lg border border-border p-4" aria-label="Recurring campaign templates">
        <h3 className="font-semibold">Recurring campaign templates</h3>
        <p className="mt-1 text-xs text-muted-foreground">Daily/weekly campaign slots are suggestions. Every occurrence needs an existing approval and an explicit operator hold. Automatic posting is disabled.</p>
        <div className="mt-3 grid gap-2 sm:grid-cols-2">
            <label className="text-sm">Approved target
                <select className="mt-1 w-full rounded border border-input bg-background p-2" value={form.target_uuid} onChange={e => setForm(f => ({...f, target_uuid: e.target.value}))}>
                    <option value="">Select target</option>
                    {approved.map(a => <option key={a.id} value={a.target_uuid}>Approval #{a.id} · {a.target_uuid.slice(0, 8)}</option>)}
                </select>
            </label>
            <label className="text-sm">Frequency
                <select className="mt-1 w-full rounded border border-input bg-background p-2" value={form.frequency} onChange={e => setForm(f => ({...f, frequency: e.target.value}))}>
                    <option value="DAILY">Daily</option><option value="WEEKLY">Weekly</option>
                </select>
            </label>
            <label className="text-sm">Interval (1–30)
                <input type="number" min="1" max="30" value={form.interval} className="mt-1 w-full rounded border border-input bg-background p-2" onChange={e => setForm(f => ({...f, interval: e.target.value}))} />
            </label>
            <label className="text-sm">IANA timezone
                <input value={form.timezone} className="mt-1 w-full rounded border border-input bg-background p-2" onChange={e => setForm(f => ({...f, timezone: e.target.value}))} />
            </label>
            <label className="text-sm">Start date and time in chosen timezone
                <input type="datetime-local" value={form.start_local} className="mt-1 w-full rounded border border-input bg-background p-2" onChange={e => setForm(f => ({...f, start_local: e.target.value}))} />
            </label>
            <label className="text-sm">Maximum occurrences (1–180)
                <input type="number" min="1" max="180" value={form.max_occurrences} className="mt-1 w-full rounded border border-input bg-background p-2" onChange={e => setForm(f => ({...f, max_occurrences: e.target.value}))} />
            </label>
        </div>
        {form.frequency === 'WEEKLY' && <fieldset className="mt-3 text-sm"><legend>Days of week</legend>
            <div className="mt-1 flex flex-wrap gap-3">{['MO','TU','WE','TH','FR','SA','SU'].map(day =>
                <label key={day} className="flex items-center gap-1"><input type="checkbox" checked={form.weekdays.includes(day)} onChange={e => setForm(f => ({...f, weekdays: e.target.checked ? [...f.weekdays, day] : f.weekdays.filter(d => d !== day)}))} />{day}</label>)}</div>
        </fieldset>}
        <Button type="button" className="mt-3" size="sm" disabled={busy || !approved.length} onClick={create}>Save recurrence template</Button>
        {!!error && <p className="mt-2 text-sm text-destructive" role="alert">{error}</p>}
        {!!message && <p className="mt-2 text-sm" role="status">{message}</p>}
        <ul className="mt-4 space-y-2">{rows.map(schedule => <li key={schedule.id} className="rounded border border-border p-3 text-sm">
            <div className="flex flex-wrap items-center justify-between gap-2"><span>#{schedule.id} · {schedule.rrule} · {schedule.state} · {schedule.timezone} · rev {schedule.version}</span>
                <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" size="sm" disabled={busy} onClick={() => showPreview(schedule)}>Preview</Button>
                    <Button type="button" variant="outline" size="sm" disabled={busy} onClick={() => showProjected(schedule)}>Projected slots</Button>
                    {schedule.state !== 'RETIRED' && <Button type="button" variant="outline" size="sm" disabled={busy} onClick={() => changeState(schedule, schedule.state === 'ACTIVE' ? 'PAUSED' : 'ACTIVE')}>{schedule.state === 'ACTIVE' ? 'Pause' : 'Resume'}</Button>}
                    {schedule.state !== 'RETIRED' && <Button type="button" variant="outline" size="sm" disabled={busy} onClick={() => changeState(schedule, 'RETIRED')}>Retire</Button>}
                </div>
            </div>
            {selected === schedule.id && current && <div className="mt-3 rounded border border-border p-3">
                <label>Preview from (UTC) <input type="date" className="ml-2 rounded border border-input bg-background px-2 py-1" value={from} onChange={e => { setFrom(e.target.value); setPreview(null); }} /></label>
                <Button type="button" variant="outline" size="sm" disabled={busy} onClick={() => showPreview(schedule)}>Refresh 30-day preview</Button>
                {preview && <p className="mt-2 text-xs">{projectionMode ? 'Stored by the optional daily expansion worker, for human review only. No new approval or held plan is implied.' : `${preview.skipped_dst ?? 0} skipped ambiguous or nonexistent daylight-saving slot(s).`} Preview never grants publishing permission.</p>}
                <ul className="mt-2 max-h-60 space-y-1 overflow-y-auto">{(preview?.slots ?? []).slice(0, 30).map(slot => <li key={slot.occurrence_key} className="flex items-center justify-between gap-2 border-b border-border py-1">
                    <span>{slot.local} · {slot.run_at}</span>
                    {slot.held && <span className="text-xs text-muted-foreground">Previously held</span>}
                    {schedule.state === 'ACTIVE' && !slot.held && approved.some(a => a.target_uuid === schedule.target_uuid) && <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => hold(schedule, slot)}>Hold only</Button>}
                </li>)}</ul>
            </div>}
        </li>)}</ul>
    </section>;
}
