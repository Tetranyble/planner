import { useCallback, useEffect, useState } from 'react';
import axios from 'axios';
import { Button } from '@/Components/ui/button';

/** Read-only third-party access: the plaintext key exists only in component memory. */
export default function IntegrationAccessPanel({ organisationUuid, brandUuid, canManage = false }) {
    const manage = `/organisations/${organisationUuid}/brands/${brandUuid}/integrations/tokens`;
    const root = `/api/v1/integrations/organisations/${organisationUuid}/brands/${brandUuid}`;
    const [name, setName] = useState('Reporting integration');
    const [days, setDays] = useState(7);
    const [enabled, setEnabled] = useState(false);
    const [grants, setGrants] = useState([]);
    const [token, setToken] = useState('');
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const refresh = useCallback(async () => {
        try {
            const { data } = await axios.get(manage);
            setEnabled(Boolean(data.data?.enabled));
            setGrants(data.data?.grants ?? []);
        } catch { setMessage('Unable to load access grants for this brand.'); }
    }, [root]);
    useEffect(() => { if (canManage) refresh(); }, [canManage, refresh]);
    useEffect(() => () => setToken(''), []);
    if (!canManage) return null;
    const issue = async (event) => {
        event.preventDefault(); setBusy(true); setMessage(''); setToken('');
        try {
            const response = await axios.post(manage, { name, expires_in_days: Number(days) });
            setToken(response.data.data?.plain_text_token ?? '');
            setMessage('Created a brand-bound, read-only token. Copy it now; it cannot be retrieved later.');
            await refresh();
        } catch (error) { setMessage(error.response?.data?.message ?? 'Token issue denied.'); }
        finally { setBusy(false); }
    };
    const revoke = async (id) => {
        setBusy(true); setMessage(''); setToken('');
        try { await axios.delete(`${manage}/${id}`); setMessage('Grant revoked.'); await refresh(); }
        catch { setMessage('Revocation failed.'); }
        finally { setBusy(false); }
    };
    return <section className="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-card" aria-label="External reporting integration access">
        <h2 className="text-lg font-semibold">Read-only integrations · UTCP / API</h2>
        <p className="mt-2 text-sm text-muted-foreground">One-user, one-brand tokens with a maximum 30-day expiry. Integration clients can inspect operations, calendar plans and provider metrics. They cannot approve, schedule, publish, or change content.</p>
        <p className="mt-2 text-xs text-muted-foreground">Connector access: {enabled ? 'Enabled for this installation' : 'Disabled by administrator (default)'}</p>
        {enabled && <form className="mt-4 flex flex-wrap items-end gap-3" onSubmit={issue}>
            <label className="text-sm">Token description<input className="mt-1 block rounded border border-input bg-background px-3 py-2" value={name} minLength={3} maxLength={100} onChange={e => setName(e.target.value)} required /></label>
            <label className="text-sm">Expiry (days)<input type="number" className="mt-1 block w-24 rounded border border-input bg-background px-3 py-2" value={days} min="1" max="30" onChange={e => setDays(e.target.value)} required /></label>
            <Button type="submit" disabled={busy}>Create read-only token</Button>
        </form>}
        {token && <div className="mt-4 rounded border border-amber-500 p-3" role="status">
            <p className="text-sm font-semibold">Token shown once</p>
            <textarea className="mt-2 w-full rounded border border-input bg-background p-2 font-mono text-xs" readOnly rows={2} aria-label="New integration token" value={token} />
            <div className="mt-2 flex items-center gap-3"><Button type="button" variant="outline" onClick={() => navigator.clipboard?.writeText(token)}>Copy token</Button><Button type="button" variant="outline" onClick={() => setToken('')}>Hide</Button></div>
        </div>}
        {message && <p role="status" className="mt-3 text-xs text-muted-foreground">{message}</p>}
        <h3 className="mt-5 font-medium">Existing grants</h3>
        <ul className="mt-2 divide-y divide-border text-sm">
            {grants.map(g => <li key={g.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                <span>{g.name} · expires {g.expires_at} · {g.revoked_at ? 'Revoked' : 'Active or expired'}</span>
                {!g.revoked_at && <Button size="sm" variant="outline" type="button" disabled={busy} onClick={() => revoke(g.id)}>Revoke</Button>}
            </li>)}
        </ul>
        <p className="mt-3 text-xs text-muted-foreground">A GET-only UTCP manual is available at <code>{`${root}/utcp`}</code> with a valid bearer token. MCP transport is not enabled until its official package is installed and separately authorized.</p>
    </section>;
}
