import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/react';

const TIMEZONES = [
    ['Africa/Lagos', 'Nigeria — Lagos'],
    ['Africa/Accra', 'Ghana — Accra'],
    ['Europe/London', 'United Kingdom — London'],
    ['America/New_York', 'United States — New York'],
    ['America/Los_Angeles', 'United States — Los Angeles'],
    ['Europe/Berlin', 'Germany — Berlin'],
    ['Asia/Dubai', 'UAE — Dubai'],
];

export default function CreateOrganisation() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        timezone: 'Africa/Lagos',
    });

    function submit(event) {
        event.preventDefault();
        post(route('organisations.store'));
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-foreground">Create organisation</h2>}>
            <Head title="Create organisation" />
            <main className="mx-auto max-w-2xl px-4 py-8 sm:px-6">
                <Link href={route('organisations.index')} className="text-sm text-muted-foreground hover:text-foreground underline">Back to organisations</Link>
                <div className="mt-6 rounded-xl border border-border bg-card p-6 shadow-card">
                    <h1 className="text-xl font-semibold text-foreground">Your new marketing workspace</h1>
                    <p className="mt-2 text-sm text-muted-foreground">You will be its owner. Roles and permissions are configured automatically before this workspace becomes available.</p>
                    <form onSubmit={submit} className="mt-6 space-y-5">
                        <div>
                            <label htmlFor="organisation-name" className="block text-sm font-medium text-foreground">Organisation name</label>
                            <input id="organisation-name" name="name" autoComplete="organization" value={data.name} onChange={e => setData('name', e.target.value)} required maxLength={120} aria-invalid={Boolean(errors.name)} aria-describedby={errors.name ? 'org-name-error' : undefined} className="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="e.g. Tetranyble Labs" />
                            {errors.name && <p id="org-name-error" role="alert" className="mt-1 text-sm text-destructive">{errors.name}</p>}
                        </div>
                        <div>
                            <label htmlFor="organisation-timezone" className="block text-sm font-medium text-foreground">Default timezone</label>
                            <select id="organisation-timezone" name="timezone" value={data.timezone} onChange={e => setData('timezone', e.target.value)} aria-invalid={Boolean(errors.timezone)} aria-describedby={errors.timezone ? 'org-timezone-error' : undefined} className="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                {TIMEZONES.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                            {errors.timezone && <p id="org-timezone-error" role="alert" className="mt-1 text-sm text-destructive">{errors.timezone}</p>}
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <Button type="submit" disabled={processing}>{processing ? 'Creating…' : 'Create organisation'}</Button>
                            <Button type="button" variant="outline" asChild><Link href={route('organisations.index')}>Cancel</Link></Button>
                        </div>
                    </form>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
