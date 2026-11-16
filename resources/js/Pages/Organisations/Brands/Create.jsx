import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreateBrand({ organisation }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        website_url: '',
        timezone: organisation.timezone,
    });

    const indexUrl = route('organisations.brands.index', { organisation: organisation.uuid });

    function submit(event) {
        event.preventDefault();
        post(route('organisations.brands.store', { organisation: organisation.uuid }));
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-foreground">Add a brand — {organisation.name}</h2>}>
            <Head title="Create brand" />
            <main className="mx-auto max-w-2xl px-4 py-8 sm:px-6">
                <Link href={indexUrl} className="text-sm text-muted-foreground hover:text-foreground underline">Back to brands</Link>
                <div className="mt-6 rounded-xl border border-border bg-card p-6 shadow-card">
                    <h1 className="text-xl font-semibold text-foreground">New brand</h1>
                    <p className="mt-2 text-sm text-muted-foreground">Your brand will be scoped to this organisation. Only authorised members will have access.</p>
                    <form onSubmit={submit} className="mt-6 space-y-5">
                        <div>
                            <label htmlFor="brand-name" className="block text-sm font-medium text-foreground">Brand name</label>
                            <input id="brand-name" name="name" value={data.name} onChange={e => setData('name', e.target.value)} required maxLength={120} aria-invalid={Boolean(errors.name)} aria-describedby={errors.name ? 'brand-name-error' : undefined} className="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="e.g. Pensuh" />
                            {errors.name && <p id="brand-name-error" role="alert" className="mt-1 text-sm text-destructive">{errors.name}</p>}
                        </div>
                        <div>
                            <label htmlFor="brand-url" className="block text-sm font-medium text-foreground">Website (optional)</label>
                            <input id="brand-url" name="website_url" inputMode="url" type="url" value={data.website_url} onChange={e => setData('website_url', e.target.value)} aria-invalid={Boolean(errors.website_url)} aria-describedby={errors.website_url ? 'brand-url-error' : undefined} className="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="https://pensuh.com" />
                            {errors.website_url && <p id="brand-url-error" role="alert" className="mt-1 text-sm text-destructive">{errors.website_url}</p>}
                        </div>
                        <p className="text-sm text-muted-foreground">The brand will use {organisation.timezone} as its initial timezone.</p>
                        {errors.timezone && <p role="alert" className="text-sm text-destructive">{errors.timezone}</p>}
                        <div className="flex flex-wrap items-center gap-3">
                            <Button type="submit" disabled={processing}>{processing ? 'Creating…' : 'Create brand'}</Button>
                            <Button type="button" variant="outline" asChild><Link href={indexUrl}>Cancel</Link></Button>
                        </div>
                    </form>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
