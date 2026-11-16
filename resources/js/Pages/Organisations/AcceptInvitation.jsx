import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Head, useForm } from '@inertiajs/react';

export default function AcceptInvitation({ organisation, token }) {
    const form = useForm({});
    const accept = (event) => {
        event.preventDefault();
        form.post(route('organisation.invitations.accept', { token }));
    };
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-foreground">Organisation invitation</h2>}>
            <Head title="Accept invitation" />
            <div className="mx-auto max-w-xl px-4 py-16">
                <section className="rounded-xl border border-border bg-card p-7 text-card-foreground">
                    <h1 className="text-2xl font-bold">Join {organisation.name}?</h1>
                    <p className="mt-3 text-sm text-muted-foreground">Accept to join this workspace using your verified email address. Your access is governed by the organisation's membership policy.</p>
                    <form onSubmit={accept} className="mt-6">
                        <Button type="submit" disabled={form.processing}>Accept invitation</Button>
                    </form>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
