import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Head, Link } from '@inertiajs/react';

export default function OrganisationBrands({ organisation, brands, canCreateBrand = false, canManageMembers = false, strategyReadableBrandUuids = [], socialReadableBrandUuids = [], contentReadableBrandUuids = [] }) {
    const { data, pagination } = brands;
    const pageUrl = (page) => route('organisations.brands.index', { organisation: organisation.uuid, page });
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-foreground">{organisation.name} — Brands</h2>}>
            <Head title={`${organisation.name} brands`} />
            <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6" aria-label="Brands directory">
                <Link href={route('organisations.index')} className="text-sm font-medium text-foreground underline">All organisations</Link>
                <div className="my-5 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-muted-foreground">Active brands you can access are displayed. Organisation owners can also see brands for access recovery.</p>
                    {canManageMembers && <Button variant="outline" asChild><Link href={route('organisations.members.index', { organisation: organisation.uuid })}>Manage members</Link></Button>}
                    {canCreateBrand && <Button asChild><Link href={route('organisations.brands.create', { organisation: organisation.uuid })}>Add brand</Link></Button>}
                </div>
                {data.length === 0 ? (
                    <div className="rounded-xl border border-border bg-card text-card-foreground p-8 text-center" role="status">
                        <h3 className="font-semibold text-foreground">No accessible brands</h3>
                        <p className="mt-2 text-sm text-muted-foreground">Contact your organisation administrator to request brand access.</p>
                    </div>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2">
                        {data.map((brand) => (
                            <li key={brand.uuid} className="rounded-xl border border-border bg-card text-card-foreground p-5 shadow-card">
                                <h3 className="text-lg font-semibold text-foreground">{brand.name}</h3>
                                <p className="mt-1 text-sm text-muted-foreground">Timezone: {brand.timezone}</p>
                                <div className="mt-3"><Button variant="outline" asChild><Link href={route('organisations.brands.members.index', { organisation: organisation.uuid, brand: brand.uuid })}>Manage brand members</Link></Button></div>
                                {strategyReadableBrandUuids.includes(brand.uuid) && <div className="mt-2"><Button variant="outline" asChild><Link href={route('organisations.brands.strategy.show', { organisation: organisation.uuid, brand: brand.uuid })}>Brand strategy</Link></Button></div>}
                                {contentReadableBrandUuids.includes(brand.uuid) && <div className="mt-2"><Button variant="outline" asChild><Link href={route('organisations.brands.content.index', { organisation: organisation.uuid, brand: brand.uuid })}>Content studio</Link></Button></div>}
                                {socialReadableBrandUuids.includes(brand.uuid) && <div className="mt-2"><Button variant="outline" asChild><Link href={route('organisations.brands.social-accounts.index', { organisation: organisation.uuid, brand: brand.uuid })}>Social accounts</Link></Button></div>}
                                {brand.website_url && <a href={brand.website_url} target="_blank" rel="noopener noreferrer" className="mt-2 inline-block text-sm underline">Open website</a>}
                            </li>
                        ))}
                    </ul>
                )}
                <div className="mt-6 flex items-center justify-between gap-3" aria-label="Pagination">
                    {pagination.page > 1 ? (
                        <Button variant="outline" asChild><Link href={pageUrl(pagination.page - 1)}>Previous</Link></Button>
                    ) : <Button variant="outline" disabled>Previous</Button>}
                    <span className="text-sm text-muted-foreground">Page {pagination.page} of {pagination.last_page}</span>
                    {pagination.page < pagination.last_page ? (
                        <Button variant="outline" asChild><Link href={pageUrl(pagination.page + 1)}>Next</Link></Button>
                    ) : <Button variant="outline" disabled>Next</Button>}
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
