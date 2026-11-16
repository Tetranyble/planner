import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';

/** Read-only, UTC-based plan calendar. A calendar entry is not a scheduled provider post. */
export default function PublicationCalendarPanel({ base }) {
    const monthNow = new Date().toISOString().slice(0, 7);
    const [month, setMonth] = useState(monthNow);
    const [plans, setPlans] = useState([]);
    const [error, setError] = useState('');
    const [stateFilter, setStateFilter] = useState('ALL');
    useEffect(() => {
        let active = true;
        const [year, number] = month.split('-').map(Number);
        const first = `${month}-01`;
        const last = new Date(Date.UTC(year, number, 0)).toISOString().slice(0, 10);
        axios.get(`${base}/publication-calendar`, { params: { from: first, to: last } })
            .then(({data}) => { if (active) { setPlans(data?.data?.plans ?? []); setError(''); } })
            .catch(() => { if (active) { setError('Unable to load publication calendar.'); setPlans([]); } });
        return () => { active = false; };
    }, [base, month]);
    const days = useMemo(() => {
        const [year, number] = month.split('-').map(Number);
        const count = new Date(Date.UTC(year, number, 0)).getUTCDate();
        const start = new Date(Date.UTC(year, number - 1, 1)).getUTCDay();
        return [...Array(start).fill(null), ...Array.from({ length: count }, (_, i) => i + 1)];
    }, [month]);
    const visible = useMemo(() => stateFilter === 'ALL' ? plans : plans.filter(job => job.state === stateFilter), [plans, stateFilter]);
    const exportCsv = () => {
        const escape = value => `"${String(value ?? '').replaceAll('"', '""')}"`;
        const body = [['job_id','date_utc','state','target_id','approval_request_id'],
            ...visible.map(job => [job.id, job.run_at, job.state, job.content_target_id, job.approval_request_id])];
        const csv = body.map(row => row.map(escape).join(',')).join('\r\n');
        const blob = new Blob([csv], {type:'text/csv;charset=utf-8'});
        const url = URL.createObjectURL(blob);
        const element = document.createElement('a'); element.href = url;
        element.download = `planner-held-plans-${month}.csv`;
        document.body.appendChild(element); element.click(); element.remove(); URL.revokeObjectURL(url);
    };
    const byDay = useMemo(() => visible.reduce((out, job) => {
        const date = String(job.run_at ?? '').slice(0, 10);
        (out[date] ??= []).push(job);
        return out;
    }, {}), [visible]);
    return <section className="mt-5 rounded-lg border border-border p-4" aria-label="Publication plan calendar">
        <div className="flex flex-wrap items-center justify-between gap-2">
            <h3 className="font-semibold">Publication plan calendar</h3>
            <label className="text-sm">Month (UTC) <input aria-label="Calendar month" type="month" className="ml-2 rounded border border-input bg-background px-2 py-1" value={month} onChange={e => setMonth(e.target.value)} /></label>
        </div>
        <p className="mt-1 text-xs text-muted-foreground">Operator plans only. Posting is disabled. Times and calendar dates are UTC.</p>
        <div className="mt-2 flex flex-wrap items-center gap-2 text-sm">
            <label>Plan state <select className="ml-2 rounded border border-input bg-background px-2 py-1" value={stateFilter} onChange={e => setStateFilter(e.target.value)}>
                {['ALL', ...new Set(plans.map(plan => plan.state))].map(state => <option key={state} value={state}>{state}</option>)}
            </select></label>
            <button type="button" onClick={exportCsv} className="rounded border border-input px-3 py-1" aria-label="Export filtered calendar CSV">Export CSV ({visible.length})</button>
        </div>
        {error && <p className="mt-2 text-sm text-destructive" role="alert">{error}</p>}
        <div className="mt-3 grid grid-cols-7 gap-1 text-center text-xs" aria-label={`Calendar ${month}`}>
            {['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(day => <span key={day} className="font-medium">{day}</span>)}
            {days.map((day, i) => {
                const key = day === null ? '' : `${month}-${String(day).padStart(2, '0')}`;
                return <div key={i} className="min-h-14 rounded border border-border p-1 text-left">
                    {day !== null && <><span className="font-medium">{day}</span>
                        {(byDay[key] ?? []).slice(0, 3).map(job => <p key={job.id} className="truncate text-[10px] text-muted-foreground" title={`Plan #${job.id}: ${job.state}`}>#{job.id} · {job.state}</p>)}
                        {(byDay[key] ?? []).length > 3 && <span className="text-[10px]">+{byDay[key].length - 3}</span>}
                    </>}
                </div>;
            })}
        </div>
    </section>;
}
