import { useEffect, useState } from 'react';
import axios from 'axios';
import { Button } from '@/Components/ui/button';
/** Manual two-person evidence review; cannot approve reposts or confirm provider state automatically. */
export default function PublicationReconciliationPanel({ base, jobId }) {
    const [data, setData] = useState(null);
    const [kind, setKind] = useState('PROVIDER_POST_LOOKUP');
    const [digest, setDigest] = useState('');
    const [reference, setReference] = useState('');
    const [summary, setSummary] = useState('');
    const [evidenceId, setEvidenceId] = useState('');
    const [disposition, setDisposition] = useState('ESCALATED');
    const [rationale, setRationale] = useState('');
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const url = `${base}/publication-operations/${jobId}/reconciliation`;
    const reload = async () => { const r = await axios.get(url); setData(r.data?.data); };
    useEffect(() => { reload().catch(() => setMessage('Unable to load reconciliation.')); }, [url]);
    const submit = async () => {
        setBusy(true); setMessage('');
        try { await axios.post(`${url}/evidence`, { evidence_kind: kind, evidence_sha256: digest, external_reference: reference || null, summary });
            await reload(); setSummary(''); setDigest(''); setReference(''); setMessage('Evidence recorded. A different reviewer must assess it.'); }
        catch (e) { setMessage(e?.response?.data?.message ?? 'Evidence could not be recorded.'); }
        finally { setBusy(false); }
    };
    const review = async () => {
        setBusy(true); setMessage('');
        try { await axios.post(`${url}/decision`, { evidence_id: Number(evidenceId), disposition, rationale });
            await reload(); setMessage('Human review recorded. The job remains UNCERTAIN; no remote action was taken.'); }
        catch (e) { setMessage(e?.response?.data?.message ?? 'Independent review could not be recorded.'); }
        finally { setBusy(false); }
    };
    return <div className="mt-4 space-y-3 rounded-lg border border-border p-4" aria-label="Publication reconciliation">
        <h4 className="font-semibold">Uncertain publication #{jobId} — reconciliation</h4>
        <p className="text-xs text-muted-foreground">Evidence and reviews are permanent. This desk does not retry, publish, or verify the platform response.</p>
        {(data?.evidence ?? []).map(e => <p key={e.id} className="text-xs">Evidence #{e.id}: {e.evidence_kind} · submitted by user #{e.submitted_by}</p>)}
        {data?.decision ? <p className="text-sm">Review: {data.decision.disposition} (reviewer #{data.decision.reviewed_by})</p> : <>
            <div className="space-y-2">
                <label className="block text-sm">Evidence kind<select className="mt-1 w-full rounded border border-input bg-background p-2" value={kind} onChange={e=>setKind(e.target.value)}>{['PROVIDER_POST_LOOKUP','PROVIDER_SUPPORT_CASE','ACCOUNT_ACTIVITY','AUDIT_EXPORT','OTHER'].map(k=><option key={k}>{k}</option>)}</select></label>
                <label className="block text-sm">SHA-256 of external evidence<input className="mt-1 w-full rounded border border-input bg-background p-2" value={digest} onChange={e=>setDigest(e.target.value)} placeholder="64 hexadecimal characters" /></label>
                <label className="block text-sm">Reference (optional)<input className="mt-1 w-full rounded border border-input bg-background p-2" value={reference} onChange={e=>setReference(e.target.value)} /></label>
                <label className="block text-sm">Summary<textarea className="mt-1 w-full rounded border border-input bg-background p-2" value={summary} onChange={e=>setSummary(e.target.value)} /></label>
                <Button type="button" size="sm" disabled={busy || !/^[a-f0-9]{64}$/i.test(digest) || summary.length < 12} onClick={submit}>Record evidence</Button>
            </div>
            {!!data?.evidence?.length && <div className="space-y-2 border-t border-border pt-3">
                <label className="block text-sm">Evidence for independent review<select className="mt-1 w-full rounded border border-input bg-background p-2" value={evidenceId} onChange={e=>setEvidenceId(e.target.value)}><option value="">Select evidence</option>{data.evidence.map(e=><option key={e.id} value={e.id}>Evidence #{e.id} — {e.evidence_kind}</option>)}</select></label>
                <label className="block text-sm">Disposition<select className="mt-1 w-full rounded border border-input bg-background p-2" value={disposition} onChange={e=>setDisposition(e.target.value)}>{['ESCALATED','PROVIDER_CONFIRMED','NOT_CONFIRMED'].map(k=><option key={k}>{k}</option>)}</select></label>
                <label className="block text-sm">Rationale<textarea className="mt-1 w-full rounded border border-input bg-background p-2" value={rationale} onChange={e=>setRationale(e.target.value)} /></label>
                <Button type="button" size="sm" disabled={busy || !evidenceId || rationale.length < 12} onClick={review}>Record independent review</Button>
            </div>}
        </>}
        {!!message && <p role="status" className="text-sm">{message}</p>}
    </div>;
}
