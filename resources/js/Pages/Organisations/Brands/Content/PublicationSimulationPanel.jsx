import { useState } from 'react';
import axios from 'axios';
import { Button } from '@/Components/ui/button';

const providers = ['facebook','instagram','linkedin','x','tiktok','youtube'];
const scenarios = ['success','rate_limit','rejected','server_error','safe_before_send','timeout','missing_receipt'];
const mockScenarios = ['mock_confirmed','mock_accepted_no_receipt','mock_rate_limited','mock_rejected','mock_server_error','mock_timeout','mock_failed_before_send'];

/** Deterministic offline sandbox. No access to published content, tokens or remote transport. */
export default function PublicationSimulationPanel({ base }) {
    const [provider,setProvider] = useState('facebook');
    const [scenario,setScenario] = useState('timeout');
    const [result,setResult] = useState(null);
    const [busy,setBusy] = useState(false);
    const [error,setError] = useState('');
    const simulate = async () => {
        setBusy(true); setError(''); setResult(null);
        try {
            const response = await axios.post(`${base}/publication-delivery/simulate`, {
                provider,scenario,attempts:1,maximum:3,idempotency_verified:false,
            });
            setResult(response.data?.data ?? null);
        } catch (e) { setError(e?.response?.data?.message ?? 'Simulation unavailable.'); }
        finally { setBusy(false); }
    };
    return <section className="mt-5 rounded-lg border border-border p-4" aria-label="Offline delivery simulation">
        <h3 className="font-semibold">Provider delivery simulator</h3>
        <p className="mt-1 text-xs text-muted-foreground">Offline simulation only. No social account is accessed, no post is sent, and no publication record is changed.</p>
        <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <label className="text-sm">Network
                <select className="mt-1 w-full rounded-lg border border-input bg-background p-2" value={provider} onChange={e => setProvider(e.target.value)}>
                    {providers.map(value => <option key={value} value={value}>{value}</option>)}
                </select>
            </label>
            <label className="text-sm">Simulated response
                <select className="mt-1 w-full rounded-lg border border-input bg-background p-2" value={scenario} onChange={e => setScenario(e.target.value)}>
                    <optgroup label="Direct simulated outcomes">{scenarios.map(value => <option key={value} value={value}>{value.replaceAll('_',' ')}</option>)}</optgroup>
                    <optgroup label="Offline mock HTTP contract">{mockScenarios.map(value => <option key={value} value={value}>{value.slice(5).replaceAll('_',' ')}</option>)}</optgroup>
                </select>
            </label>
        </div>
        <Button className="mt-3" type="button" variant="outline" disabled={busy} onClick={simulate}>{busy ? 'Checking…' : 'Run offline simulation'}</Button>
        {error && <p role="alert" className="mt-2 text-sm text-destructive">{error}</p>}
        {result && <div className="mt-3 rounded-md bg-muted p-3 text-sm" aria-live="polite">
            <p>Outcome: <strong>{result.outcome.kind}</strong> ({result.outcome.code})</p>
            <p>Safety decision: <strong>{result.policy.action}</strong></p>
            {scenario.startsWith('mock_') && <p className="text-xs text-muted-foreground">Synthetic HTTP-shaped exchange; no real provider credentials, endpoints, or receipts.</p>}
            <p>Network calls: {result.network_requests} · Database writes: {result.database_writes}</p>
            <p className="mt-1 text-xs text-muted-foreground">Live dispatch remains blocked: {result.gate.blockers.join(', ')}</p>
        </div>}
    </section>;
}
