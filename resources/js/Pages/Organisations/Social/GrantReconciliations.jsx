import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';

function EvidenceForm({ item, organisation }) {
    const form = useForm({ source_type: 'PROVIDER_SUPPORT', reference: '', summary: '' });
    const submit = (event) => {
        event.preventDefault();
        form.post(route('organisations.grants.reconciliations.store', {
            organisation: organisation.uuid, requestUuid: item.uuid,
        }), { preserveScroll: true, onSuccess: () => form.reset('reference','summary') });
    };
    return <form onSubmit={submit} className="space-y-3 rounded-lg border border-border bg-muted/30 p-4">
        <h4 className="font-medium">Submit external evidence</h4>
        <p className="text-xs text-muted-foreground">Provide a case number or vetted URL, not tokens, secrets, or personal provider identifiers. Evidence alone never closes the case.</p>
        <label className="grid gap-1 text-sm">Evidence source
            <select className="rounded-md border border-input bg-background p-2" value={form.data.source_type}
                onChange={e => form.setData('source_type', e.target.value)}>
                <option value="PROVIDER_SUPPORT">Provider support ticket</option>
                <option value="PROVIDER_CONSOLE">Provider developer console</option>
                <option value="PROVIDER_RECEIPT">Provider response reference</option>
                <option value="INVESTIGATION">Internal investigation (not confirmation)</option>
            </select>
        </label>
        <label className="grid gap-1 text-sm">Reference
            <input type="text" required minLength={8} maxLength={512}
                className="rounded-md border border-input bg-background p-2"
                value={form.data.reference} onChange={e => form.setData('reference',e.target.value)}
                placeholder="e.g. provider-case/TKT-12345" />
        </label>
        <label className="grid gap-1 text-sm">Evidence summary
            <textarea required minLength={30} maxLength={4000} rows={3}
                className="rounded-md border border-input bg-background p-2"
                value={form.data.summary} onChange={e => form.setData('summary',e.target.value)}
                placeholder="What did the provider confirm? How was the grant identity and app verified?" />
        </label>
        {Object.entries(form.errors).map(([field,message]) => <p role="alert" className="text-sm text-destructive" key={field}>{message}</p>)}
        <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : 'Submit evidence'}</Button>
    </form>;
}

function DecisionForm({ item, organisation }) {
    const form = useForm({ evidence_uuid: item.latest_evidence?.uuid ?? '', outcome: 'ESCALATED', rationale: '' });
    const submit = (event) => {
        event.preventDefault();
        if (!window.confirm('This records an independent manual decision. It is not a provider API acknowledgement. Continue?')) return;
        form.transform(data => ({ ...data, evidence_uuid: item.latest_evidence.uuid }))
            .post(route('organisations.grants.reconciliations.decide', {
                organisation: organisation.uuid, requestUuid: item.uuid,
            }), { preserveScroll: true });
    };
    if (!item.latest_evidence) return <p className="text-sm text-muted-foreground">Awaiting initial evidence submission.</p>;
    if (!item.can_decide) return <p role="status" className="text-sm text-amber-700 dark:text-amber-300">Awaiting review by a different organisation owner.</p>;
    return <form onSubmit={submit} className="space-y-3 rounded-lg border border-border bg-card p-4">
        <h4 className="font-medium">Independent review</h4>
        <p className="text-sm text-muted-foreground">{item.latest_evidence.source_type}: {item.latest_evidence.reference}</p>
        <p className="text-sm">{item.latest_evidence.summary}</p>
        <label className="grid gap-1 text-sm">Review outcome
            <select className="rounded-md border border-input bg-background p-2" value={form.data.outcome}
                onChange={e => form.setData('outcome',e.target.value)}>
                <option value="ESCALATED">Escalate: continue investigation</option>
                <option value="ATTESTED_REVOKED">Human attestation: provider grant was revoked</option>
                <option value="CONFIRMED_ACTIVE">Provider grant still active: require fresh authorization</option>
            </select>
        </label>
        <label className="grid gap-1 text-sm">Review rationale
            <textarea required minLength={30} maxLength={4000} rows={3}
                className="rounded-md border border-input bg-background p-2"
                value={form.data.rationale} onChange={e => form.setData('rationale',e.target.value)}
                placeholder="Describe independently checked evidence and any limitations." />
        </label>
        {Object.entries(form.errors).map(([field,message]) => <p role="alert" className="text-sm text-destructive" key={field}>{message}</p>)}
        <Button type="submit" disabled={form.processing} variant="outline">{form.processing ? 'Recording…' : 'Record independent decision'}</Button>
    </form>;
}

export default function GrantReconciliations({ organisation, cases = [] }) {
    return <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-foreground">Grant reconciliation · {organisation.name}</h2>}>
        <Head title={`OAuth grant reconciliation · ${organisation.name}`} />
        <main className="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
            <Link href={route('organisations.brands.index',{organisation:organisation.uuid})} className="text-sm font-medium text-primary underline">Back to brands</Link>
            <header className="space-y-2">
                <h1 className="text-2xl font-semibold">OAuth revocation reconciliation desk</h1>
                <p className="text-sm text-muted-foreground">Resolve uncertain provider revocations using independent evidence review. No request here initiates another remote revocation, and human attestations are never represented as API acknowledgements.</p>
            </header>
            {cases.length === 0 ? <p className="rounded-lg border border-border bg-card p-5 text-muted-foreground">No unresolved manual or uncertain grant revocations.</p> :
                <ul className="space-y-6">{cases.map(item => <li key={item.uuid} className="space-y-4 rounded-xl border border-border bg-card p-5">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h3 className="font-semibold">{item.provider} · {item.affected_connections} linked connections</h3>
                        <span className="rounded-full bg-amber-100 px-3 py-1 text-xs text-amber-950 dark:bg-amber-950 dark:text-amber-100">{item.status}</span>
                    </div>
                    <p className="text-sm text-muted-foreground">Reason: {item.reason}</p>
                    {item.safe_error_code && <p className="text-xs text-muted-foreground">Code: {item.safe_error_code}</p>}
                    <EvidenceForm item={item} organisation={organisation}/>
                    <DecisionForm item={item} organisation={organisation}/>
                </li>)}</ul>}
        </main>
    </AuthenticatedLayout>;
}
