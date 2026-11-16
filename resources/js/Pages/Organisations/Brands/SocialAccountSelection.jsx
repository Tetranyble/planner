import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/react';

export default function SocialAccountSelection({ organisation, brand, selection }) {
    const form = useForm({ account_ids: [] });
    const toggle = (id) => form.setData('account_ids', form.data.account_ids.includes(id)
        ? form.data.account_ids.filter(value => value !== id)
        : [...form.data.account_ids, id]);
    const confirm = (event) => {
        event.preventDefault();
        form.post(route('organisations.brands.social-selections.confirm', {
            organisation: organisation.uuid, brand: brand.uuid, selectionUuid: selection.uuid,
        }));
    };
    return <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-foreground">Select accounts · {brand.name}</h2>}>
        <Head title="Choose social accounts" />
        <main className="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6">
            <Link className="text-sm text-primary underline" href={route('organisations.brands.social-accounts.index', {
                organisation: organisation.uuid, brand: brand.uuid,
            })}>Back to social accounts</Link>
            <div>
                <h1 className="text-2xl font-semibold">Choose which accounts belong to {brand.name}</h1>
                <p className="mt-2 text-sm text-muted-foreground">Your {selection.provider} login can access several accounts. Only those selected here will be assigned to this brand. Your pending authorisation expires at {new Date(selection.expires_at).toLocaleString()}.</p>
            </div>
            <form onSubmit={confirm} className="space-y-4">
                <fieldset className="space-y-2" aria-describedby="selection-help">
                    <legend className="text-sm font-semibold">Available accounts</legend>
                    <p id="selection-help" className="text-sm text-muted-foreground">Select one or more accounts. Existing assignments to other brands cannot be overwritten.</p>
                    {selection.accounts.map(account => <label key={account.id} className="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-card p-4">
                        <input type="checkbox" className="size-4 accent-primary" checked={form.data.account_ids.includes(account.id)} onChange={() => toggle(account.id)} />
                        <span className="flex-1">
                            <span className="block font-medium text-card-foreground">{account.name}</span>
                            <span className="block text-xs text-muted-foreground">{account.kind} {account.handle ? ` · ${account.handle}` : ''}</span>
                        </span>
                    </label>)}
                </fieldset>
                {form.errors.account_ids && <p role="alert" className="text-sm text-destructive">{form.errors.account_ids}</p>}
                <Button type="submit" disabled={form.processing || form.data.account_ids.length === 0}>
                    {form.processing ? 'Connecting…' : `Connect ${form.data.account_ids.length} selected account(s)`}
                </Button>
            </form>
        </main>
    </AuthenticatedLayout>;
}
