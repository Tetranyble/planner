import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const roleRanks = { viewer: 1, contributor: 2, manager: 3, owner: 4 };
const eligibleRoles = (roles, maxRole) => roles.filter(r => roleRanks[r.name] <= (roleRanks[maxRole] ?? 0));

function BrandMemberRow({ organisation, brand, member, roles, canManageOwners }) {
    const roleForm = useForm({ role: member.role });
    const removal = useForm({});
    const path = { organisation: organisation.uuid, brand: brand.uuid, userId: member.id };

    function changeRole(event) {
        event.preventDefault();
        roleForm.patch(route('organisations.brands.members.change-role', path), { preserveScroll: true });
    }

    function remove() {
        if (!window.confirm(`Remove ${member.name} from ${brand.name}?`)) return;
        removal.delete(route('organisations.brands.members.revoke', path), { preserveScroll: true });
    }

    return (
        <li className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0">
                <p className="font-semibold text-foreground">{member.name}</p>
                <p className="break-all text-sm text-muted-foreground">{member.email}</p>
                <p className="mt-1 text-xs text-muted-foreground">{member.state}</p>
            </div>
            {member.state === 'ACTIVE' && (member.role !== 'owner' || canManageOwners) && (
                <div className="flex flex-wrap items-end gap-3">
                    <form onSubmit={changeRole} className="flex items-end gap-2">
                        <div>
                            <label htmlFor={`role-${member.id}`} className="mb-1 block text-xs font-medium">Brand role</label>
                            <select id={`role-${member.id}`} className="h-10 rounded-md border border-input bg-background px-3 text-foreground" value={roleForm.data.role} onChange={e => roleForm.setData('role', e.target.value)}>
                                {eligibleRoles(roles, member.organisation_role).map(r => <option key={r.name} value={r.name}>{r.label}</option>)}
                            </select>
                        </div>
                        <Button type="submit" variant="outline" disabled={roleForm.processing || roleForm.data.role === member.role}>Update</Button>
                    </form>
                    <Button type="button" variant="destructive" disabled={removal.processing} onClick={remove}>Remove</Button>
                </div>
            )}
            {roleForm.errors.role && <p role="alert" className="text-sm text-destructive">{roleForm.errors.role}</p>}
        </li>
    );
}

export default function BrandMembers({ organisation, brand, directory }) {
    const form = useForm({ user_id: directory.candidates[0]?.id?.toString() ?? '', role: 'viewer' });
    const [search, setSearch] = useState(directory.candidate_search ?? '');
    const candidate = directory.candidates.find(c => String(c.id) === String(form.data.user_id));
    useEffect(() => {
        if (!directory.candidates.some(c => String(c.id) === String(form.data.user_id))) {
            form.setData('user_id', directory.candidates[0]?.id?.toString() ?? '');
        }
    }, [directory.candidates]);
    const pageUrl = (page) => route('organisations.brands.members.index', { organisation: organisation.uuid, brand: brand.uuid, page });

    function addMember(event) {
        event.preventDefault();
        form.post(route('organisations.brands.members.add', { organisation: organisation.uuid, brand: brand.uuid }), {
            preserveScroll: true,
        });
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-foreground">{brand.name} · Members</h2>}>
            <Head title={`${brand.name} members`} />
            <main className="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6">
                <Link className="text-sm font-medium text-foreground underline" href={route('organisations.brands.index', { organisation: organisation.uuid })}>Back to brands</Link>
                <section aria-labelledby="add-brand-member" className="rounded-xl border border-border bg-card p-6">
                    <h3 id="add-brand-member" className="text-lg font-semibold text-card-foreground">Give an existing organisation member access</h3>
                    <p className="mt-1 text-sm text-muted-foreground">Users must first join the organisation. Brand roles never expand their organisation permissions.</p>
                    <form className="mt-4 flex flex-wrap gap-2" onSubmit={event => {
                        event.preventDefault();
                        router.get(route('organisations.brands.members.index', { organisation: organisation.uuid, brand: brand.uuid }), { search }, { preserveState: true, replace: true });
                    }}>
                        <label htmlFor="candidate-search" className="sr-only">Search organisation members</label>
                        <input id="candidate-search" type="search" maxLength={100} placeholder="Search name or email" className="h-10 min-w-44 flex-1 rounded-md border border-input bg-background px-3 text-foreground" value={search} onChange={event => setSearch(event.target.value)} />
                        <Button variant="outline" type="submit">Search members</Button>
                    </form>
                    {directory.candidates.length ? (
                        <form onSubmit={addMember} className="mt-5 flex flex-wrap items-end gap-3">
                            <div className="min-w-48 flex-1">
                                <label htmlFor="brand-member-user" className="mb-1 block text-sm font-medium">Member</label>
                                <select id="brand-member-user" required className="h-10 w-full rounded-md border border-input bg-background px-3 text-foreground" value={form.data.user_id} onChange={e => form.setData('user_id', e.target.value)}>
                                    {directory.candidates.map(u => <option key={u.id} value={u.id}>{u.name} · {u.email}</option>)}
                                </select>
                                {form.errors.user_id && <p role="alert" className="text-sm text-destructive">{form.errors.user_id}</p>}
                            </div>
                            <div>
                                <label htmlFor="brand-member-role" className="mb-1 block text-sm font-medium">Role</label>
                                <select id="brand-member-role" value={form.data.role} onChange={e => form.setData('role', e.target.value)} className="h-10 rounded-md border border-input bg-background px-3 text-foreground">
                                    {eligibleRoles(directory.roles, candidate?.organisation_role).map(role => <option key={role.name} value={role.name}>{role.label}</option>)}
                                </select>
                                {form.errors.role && <p role="alert" className="text-sm text-destructive">{form.errors.role}</p>}
                            </div>
                            <Button type="submit" disabled={form.processing}>Add to brand</Button>
                        </form>
                    ) : <p className="mt-4 text-sm text-muted-foreground">No additional eligible organisation members. Invite people at the organisation level first.</p>}
                </section>
                <section aria-labelledby="active-members">
                    <h3 id="active-members" className="mb-3 text-lg font-semibold">Brand memberships ({directory.pagination.total})</h3>
                    {directory.members.length === 0 ? <p className="text-muted-foreground">No members yet.</p> :
                        <ul className="space-y-3">{directory.members.map(m => <BrandMemberRow key={m.id} organisation={organisation} brand={brand} member={m} roles={directory.roles} canManageOwners={directory.can_manage_owners} />)}</ul>}
                    <div className="mt-5 flex items-center justify-between gap-2 text-sm text-muted-foreground">
                        {directory.pagination.page > 1 ? <Button variant="outline" asChild><Link href={pageUrl(directory.pagination.page - 1)}>Previous</Link></Button> : <Button variant="outline" disabled>Previous</Button>}
                        <span>Page {directory.pagination.page} of {directory.pagination.last_page}</span>
                        {directory.pagination.page < directory.pagination.last_page ? <Button variant="outline" asChild><Link href={pageUrl(directory.pagination.page + 1)}>Next</Link></Button> : <Button variant="outline" disabled>Next</Button>}
                    </div>
                </section>
            </main>
        </AuthenticatedLayout>
    );
}
