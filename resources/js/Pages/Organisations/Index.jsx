import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Head, Link } from '@inertiajs/react';

export default function OrganisationsIndex({ organisations }) {
    const { data, pagination } = organisations;
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-foreground">Your organisations</h2>}>
            <Head title="Organisations" />
            <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6" aria-label="Organisations directory">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-muted-foreground">Only active organisations where you are an active member appear here.</p>
                    <Button asChild><Link href={route('organisations.create')}>Create organisation</Link></Button>
                </div>
                {data.length === 0 ? (
                    <div className="rounded-xl border border-border bg-card text-card-foreground p-8 text-center" role="status">
                        <h3 className="font-semibold text-foreground">No active organisations</h3>
                        <p className="mt-2 text-sm text-muted-foreground">Create a workspace to start managing brands, or ask an organisation owner to invite you.</p>
                    </div>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2">
                        {data.map((organisation) => (
                            <li key={organisation.uuid} className="rounded-xl border border-border bg-card text-card-foreground p-5 shadow-card">
                                <h3 className="text-lg font-semibold text-foreground">{organisation.name}</h3>
                                <p className="mt-1 text-sm text-muted-foreground">Timezone: {organisation.timezone}</p>
                                <Button asChild className="mt-4">
                                    <Link href={route('organisations.brands.index', { organisation: organisation.uuid })}>View brands</Link>
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
                <div className="mt-6 flex items-center justify-between gap-3" aria-label="Pagination">
                    {pagination.page > 1 ? (
                        <Button variant="outline" asChild><Link href={route('organisations.index', { page: pagination.page - 1 })}>Previous</Link></Button>
                    ) : <Button variant="outline" disabled>Previous</Button>}
                    <span className="text-sm text-muted-foreground">Page {pagination.page} of {pagination.last_page}</span>
                    {pagination.page < pagination.last_page ? (
                        <Button variant="outline" asChild><Link href={route('organisations.index', { page: pagination.page + 1 })}>Next</Link></Button>
                    ) : <Button variant="outline" disabled>Next</Button>}
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
