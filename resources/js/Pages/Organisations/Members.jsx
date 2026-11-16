import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/react';

function MemberRow({ member, organisation, roles, canManageOwners }) {
    const roleForm = useForm({ role: member.role });
    const revokeForm = useForm({});
    const updateRole = (event) => {
        event.preventDefault();
        roleForm.patch(route('organisations.members.change-role', {
            organisation: organisation.uuid, userId: member.id,
        }), { preserveScroll: true });
    };
    const revoke = () => {
        if (!window.confirm(`Revoke ${member.name}'s organisation access? This also removes their brand access.`)) return;
        revokeForm.delete(route('organisations.members.revoke', {
            organisation: organisation.uuid, userId: member.id,
        }), { preserveScroll: true });
    };
    return (
        <li className="rounded-xl border border-border bg-card p-4" aria-label={`Membership for ${member.name}`}>
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="min-w-0">
                    <p className="font-semibold text-card-foreground">{member.name}</p>
                    <p className="break-all text-sm text-muted-foreground">{member.email}</p>
                    <p className="mt-1 text-xs text-muted-foreground">Status: {member.state}</p>
                </div>
                {member.state === 'ACTIVE' && (member.role !== 'owner' || canManageOwners) && (
                    <div className="flex flex-wrap items-end gap-2">
                        <form onSubmit={updateRole} className="flex items-end gap-2">
                            <div>
                                <label htmlFor={`role-${member.id}`} className="mb-1 block text-xs font-medium text-foreground">Role</label>
                                <select id={`role-${member.id}`} className="h-10 rounded-md border border-input bg-background px-2 text-sm text-foreground" value={roleForm.data.role} onChange={e => roleForm.setData('role', e.target.value)}>
                                    {roles.map(r => <option key={r.name} value={r.name}>{r.label}</option>)}
                                </select>
                            </div>
                            <Button type="submit" variant="outline" disabled={roleForm.processing || roleForm.data.role === member.role}>Update</Button>
                        </form>
                        <Button type="button" variant="destructive" onClick={revoke} disabled={revokeForm.processing}>Revoke</Button>
                    </div>
                )}
            </div>
            {roleForm.errors.role && <p role="alert" className="mt-2 text-sm text-destructive">{roleForm.errors.role}</p>}
        </li>
    );
}

function InvitationRow({ invitation, organisation }) {
    const cancellation = useForm({});
    const cancel = () => {
        if (!window.confirm(`Cancel invitation to ${invitation.email}?`)) return;
        cancellation.delete(route('organisations.members.cancel-invitation', {
            organisation: organisation.uuid, uuid: invitation.uuid,
        }), { preserveScroll: true });
    };
    return (
        <li className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card p-3">
            <div>
                <p className="font-medium text-foreground">{invitation.email}</p>
                <p className="text-xs text-muted-foreground">Email: {invitation.delivery_state} · Expires {new Date(invitation.expires_at).toLocaleString()}</p>
            </div>
            <Button variant="outline" onClick={cancel} disabled={cancellation.processing}>Cancel invitation</Button>
        </li>
    );
}

export default function OrganisationMembers({ organisation, directory, currentUserId }) {
    const inviteForm = useForm({ email: '', role: 'contributor' });
    const invite = (event) => {
        event.preventDefault();
        inviteForm.post(route('organisations.members.invite', { organisation: organisation.uuid }), {
            preserveScroll: true,
            onSuccess: () => inviteForm.reset('email'),
        });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-foreground">{organisation.name} · Members</h2>}>
            <Head title={`Members · ${organisation.name}`} />
            <div className="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6">
                <Link className="text-sm font-medium text-foreground underline" href={route('organisations.brands.index', { organisation: organisation.uuid })}>Back to brands</Link>
                <section aria-labelledby="invite-heading" className="rounded-xl border border-border bg-card p-6">
                    <h3 id="invite-heading" className="text-lg font-semibold text-card-foreground">Invite a teammate</h3>
                    <p className="mt-1 text-sm text-muted-foreground">Invitations expire after seven days. The recipient must verify the invited email address.</p>
                    <form onSubmit={invite} className="mt-5 grid gap-4 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                        <div>
                            <label htmlFor="invite-email" className="mb-1 block text-sm font-medium text-foreground">Email address</label>
                            <input id="invite-email" type="email" required autoComplete="email" value={inviteForm.data.email} onChange={e => inviteForm.setData('email', e.target.value)} aria-invalid={Boolean(inviteForm.errors.email)} className="h-10 w-full rounded-md border border-input bg-background px-3 text-foreground" />
                            {inviteForm.errors.email && <p role="alert" className="mt-1 text-sm text-destructive">{inviteForm.errors.email}</p>}
                        </div>
                        <div>
                            <label htmlFor="invite-role" className="mb-1 block text-sm font-medium text-foreground">Role</label>
                            <select id="invite-role" value={inviteForm.data.role} onChange={e => inviteForm.setData('role', e.target.value)} className="h-10 w-full rounded-md border border-input bg-background px-2 text-foreground">
                                {directory.roles.map(role => <option key={role.name} value={role.name}>{role.label}</option>)}
                            </select>
                        </div>
                        <Button type="submit" disabled={inviteForm.processing}>Send invitation</Button>
                    </form>
                </section>
                <section aria-labelledby="members-heading">
                    <h3 id="members-heading" className="mb-3 text-lg font-semibold text-foreground">Members ({directory.members_page.total})</h3>
                    <ul className="space-y-3">
                        {directory.members.map(member => <MemberRow key={member.id} member={member} organisation={organisation} roles={directory.roles} canManageOwners={directory.can_manage_owners} />)}
                    </ul>
                    <div className="mt-4 flex items-center justify-between gap-2 text-sm text-muted-foreground" aria-label="Member pages">
                        {directory.members_page.page > 1 ? <Button variant="outline" asChild><Link href={route('organisations.members.index', { organisation: organisation.uuid, page: directory.members_page.page - 1 })}>Previous</Link></Button> : <Button variant="outline" disabled>Previous</Button>}
                        <span>Page {directory.members_page.page} of {directory.members_page.last_page}</span>
                        {directory.members_page.page < directory.members_page.last_page ? <Button variant="outline" asChild><Link href={route('organisations.members.index', { organisation: organisation.uuid, page: directory.members_page.page + 1 })}>Next</Link></Button> : <Button variant="outline" disabled>Next</Button>}
                    </div>
                </section>
                <section aria-labelledby="pending-heading">
                    <h3 id="pending-heading" className="mb-3 text-lg font-semibold text-foreground">Pending invitations ({directory.invitations.length})</h3>
                    {directory.invitations.length === 0 ? <p className="text-sm text-muted-foreground">No outstanding invitations.</p> :
                        <ul className="space-y-2">{directory.invitations.map(inv => <InvitationRow key={inv.uuid} invitation={inv} organisation={organisation} />)}</ul>}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
