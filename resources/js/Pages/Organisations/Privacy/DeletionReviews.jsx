import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';

const reasons = {
    BRAND_CONTENT_REVIEW: 'Review content and identify any personal information requiring removal or lawful retention.',
    NEW_GRANT_CONFLICT: 'A new OAuth grant was detected. Revoke or reconcile it before attesting to deletion.',
};

function ReviewForm({ item, organisation }) {
    const form = useForm({
        disposition: 'CLEARED',
        explanation: '',
        evidence_reference: '',
        legal_basis: '',
        review_again_at: '',
    });
    const submit = (event) => {
        event.preventDefault();
        if (!window.confirm('Record this privacy decision? It will become an immutable audit event.')) return;
        form.post(route('organisations.privacy.deletion-reviews.resolve', {
            organisation: organisation.uuid, targetId: item.id,
        }), { preserveScroll: true });
    };
    const retained = form.data.disposition === 'RETAINED_LEGAL';
    return <li className="rounded-xl border border-border bg-card p-5 text-card-foreground">
        <div className="flex flex-wrap items-center justify-between gap-2">
            <h3 className="font-semibold">Review #{item.id}</h3>
            <span className="rounded-full bg-amber-100 px-2 py-1 text-xs text-amber-900 dark:bg-amber-950 dark:text-amber-100">Requires attention</span>
        </div>
        <p className="mt-2 text-sm text-muted-foreground">{reasons[item.review_reason] ?? 'Verify the remaining deletion obligations and access restrictions.'}</p>
        <form className="mt-4 grid gap-3" onSubmit={submit}>
            <label className="grid gap-1 text-sm">Decision
                <select className="rounded-md border border-input bg-background p-2" value={form.data.disposition}
                    onChange={e => form.setData('disposition', e.target.value)}>
                    <option value="CLEARED">Verified all attributable personal data removed</option>
                    <option value="RETAINED_LEGAL">Specific records retained under documented legal basis</option>
                </select>
            </label>
            {retained && <>
                <label className="grid gap-1 text-sm">Legal basis
                    <select required className="rounded-md border border-input bg-background p-2" value={form.data.legal_basis}
                        onChange={e => form.setData('legal_basis', e.target.value)}>
                        <option value="">Select a legal basis</option>
                        <option value="LEGAL_OBLIGATION">Legal obligation</option>
                        <option value="LEGAL_CLAIMS">Establishment, exercise or defence of legal claims</option>
                        <option value="REGULATORY_HOLD">Regulatory hold</option>
                    </select>
                </label>
                <label className="grid gap-1 text-sm">Retention review date
                    <input type="date" required className="rounded-md border border-input bg-background p-2"
                        value={form.data.review_again_at} onChange={e => form.setData('review_again_at', e.target.value)}/>
                </label>
            </>}
            <label className="grid gap-1 text-sm">Evidence reference (internal secure reference, not personal data)
                <input required minLength={8} maxLength={512} className="rounded-md border border-input bg-background p-2"
                    value={form.data.evidence_reference} onChange={e => form.setData('evidence_reference', e.target.value)}
                    placeholder="e.g. privacy-case/TKT-1234" />
            </label>
            <label className="grid gap-1 text-sm">Reasoned explanation
                <textarea required minLength={30} maxLength={4000} rows={3} className="rounded-md border border-input bg-background p-2"
                    value={form.data.explanation} onChange={e => form.setData('explanation', e.target.value)}
                    placeholder="Specify systems reviewed and evidence of erasure, or precise legal retention justification." />
            </label>
            {Object.entries(form.errors).map(([field,message]) => <p role="alert" className="text-sm text-destructive" key={field}>{message}</p>)}
            <Button type="submit" disabled={form.processing}>{form.processing ? 'Recording…' : 'Record decision'}</Button>
        </form>
    </li>;
}

function RetentionFollowupForm({ item, organisation }) {
    const form = useForm({ notes: '' });
    const submit = (event) => {
        event.preventDefault();
        if (!window.confirm('Acknowledge this retention review? This will not erase data.')) return;
        form.post(route('organisations.privacy.retention-reviews.acknowledge', {
            organisation: organisation.uuid, followupId: item.id,
        }), { preserveScroll: true });
    };
    return <li className="rounded-xl border border-border bg-card p-5">
        <h3 className="font-semibold">Retention review #{item.id}</h3>
        <p className="mt-2 text-sm text-muted-foreground">Due: {item.due_at ? new Date(item.due_at).toLocaleDateString() : 'Now'}</p>
        <p className="mt-1 text-sm text-muted-foreground">{item.action_required}</p>
        <form onSubmit={submit} className="mt-3 space-y-3">
            <label className="block text-sm">Review notes (minimum 30 characters)
                <textarea required minLength={30} maxLength={4000} rows={3}
                    className="mt-1 w-full rounded-md border border-input bg-background p-2"
                    value={form.data.notes} onChange={e => form.setData('notes',e.target.value)} />
            </label>
            {form.errors.notes && <p role="alert" className="text-sm text-destructive">{form.errors.notes}</p>}
            <Button type="submit" disabled={form.processing}>Acknowledge follow-up</Button>
        </form>
    </li>;
}

export default function DeletionReviews({ organisation, targets, retentionFollowups = [] }) {
    return <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-foreground">Privacy reviews · {organisation.name}</h2>}>
        <Head title={`Privacy reviews · ${organisation.name}`} />
        <main className="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6">
            <Link className="text-sm text-primary underline" href={route('organisations.index')}>Back to organisations</Link>
            <div><h1 className="text-2xl font-semibold">Provider data-deletion reviews</h1>
                <p className="mt-2 text-sm text-muted-foreground">Only organisation owners can resolve outstanding privacy items. Actions are audited. Never copy sensitive provider identifiers into notes.</p>
            </div>
            <section aria-labelledby="retention-review-heading" className="space-y-4">
                <div>
                    <h2 id="retention-review-heading" className="text-lg font-semibold">Lawful retention follow-ups</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Acknowledging a due review does not certify deletion or extend legal authority. Re-evaluate the basis for retaining each record.</p>
                </div>
                {retentionFollowups.length === 0 ? <p className="rounded-lg border border-border bg-card p-4 text-sm text-muted-foreground">No retention reviews are currently due.</p> :
                    <ul className="space-y-3">{retentionFollowups.map(item => <RetentionFollowupForm key={item.id} item={item} organisation={organisation} />)}</ul>}
            </section>
            {targets.length === 0 ? <p className="rounded-lg border border-border bg-card p-5 text-muted-foreground">No outstanding deletion reviews for this organisation.</p> :
                <ul className="space-y-4">{targets.map(item => <ReviewForm key={item.id} item={item} organisation={organisation}/>)}</ul>}
        </main>
    </AuthenticatedLayout>;
}
