import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Head, Link, router, useForm } from '@inertiajs/react';

const providers = [
    { id: 'facebook', label: 'Facebook Pages', description: 'Discover Pages you administer. Publishing requires additional Meta permissions.' },
    { id: 'instagram', label: 'Instagram', description: 'Discover professional Instagram accounts linked to your Facebook Pages.' },
    { id: 'linkedin', label: 'LinkedIn', description: 'Connect your profile. Company Page discovery requires approved LinkedIn API access.' },
    { id: 'x', label: 'X (Twitter)', description: 'Connect your X account securely using OAuth 2.0 and PKCE.' },
    { id: 'tiktok', label: 'TikTok', description: 'Discover your creator profile. Direct publishing remains approval-dependent.' },
    { id: 'youtube', label: 'YouTube', description: 'Discover channels using YouTube read-only permission. Uploads are not enabled.' },
];

function ConnectionCard({ provider, available, organisation, brand }) {
    const start = () => router.post(route('organisations.brands.social-connections.start', {
        organisation: organisation.uuid, brand: brand.uuid, provider: provider.id,
    }), {}, { preserveScroll: true });
    return <article className="rounded-xl border border-border bg-card p-5 text-card-foreground">
        <h2 className="font-semibold">{provider.label}</h2>
        <p className="mt-2 min-h-12 text-sm text-muted-foreground">{provider.description}</p>
        <Button type="button" onClick={start} disabled={!available} className="mt-4">
            {available ? `Connect ${provider.label}` : 'Provider not configured'}
        </Button>
    </article>;
}

function SocialAccountCard({ account, organisation, brand, canRevokeGrants, canVerifyPublishing }) {
    const removal = useForm({});
    const disconnect = () => {
        if (!window.confirm(`Disconnect ${account.display_name} locally? This does not revoke the provider grant.`)) return;
        removal.delete(route('organisations.brands.social-accounts.destroy', {
            organisation: organisation.uuid, brand: brand.uuid, accountUuid: account.uuid,
        }), { preserveScroll: true });
    };
    const requestGrantRevocation = () => {
        const reason = window.prompt(`Revoke the entire ${account.provider} authorization? This may affect other brand connections. Explain why:`);
        if (reason === null) return;
        router.post(route('organisations.grants.revocations.store', {
            organisation: organisation.uuid, brand: brand.uuid, accountUuid: account.uuid,
        }), { reason }, { preserveScroll: true });
    };
    const verifyPublishing = () => router.post(route('organisations.brands.social-accounts.verify-publishing', {
        organisation: organisation.uuid, brand: brand.uuid, accountUuid: account.uuid,
    }), {}, { preserveScroll: true });
    const needsReconnect = account.connection?.status === 'REAUTH_REQUIRED';
    const reconnect = () => router.post(route('organisations.brands.social-connections.start', {
        organisation: organisation.uuid, brand: brand.uuid, provider: account.provider,
    }), {}, { preserveScroll: true });
    return <li className="rounded-xl border border-border bg-card p-5 text-card-foreground">
        <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p className="font-semibold">{account.display_name}</p>
                <p className="text-sm text-muted-foreground">{account.provider} · {account.account_kind} · {account.status}</p>
                {account.handle && <p className="text-xs text-muted-foreground">{account.handle}</p>}
                {needsReconnect && <p role="status" className="mt-2 text-sm text-destructive">
                    Authorization expired or was revoked. Reconnect to restore provider access.
                </p>}
                {account.connection?.expires_at && <p className="mt-1 text-xs text-muted-foreground">
                    Token expiry: {new Date(account.connection.expires_at).toLocaleString()}
                </p>}
            </div>
            <div className="flex flex-wrap gap-2">
                {canVerifyPublishing && ['facebook','linkedin','x'].includes(account.provider) && account.status === 'ACTIVE' && <Button type="button" variant="outline" onClick={verifyPublishing}>Verify publishing permission</Button>}
                {needsReconnect && <Button type="button" onClick={reconnect}>Reconnect {account.provider}</Button>}
                {account.status !== 'ARCHIVED' && <Button type="button" variant="outline" disabled={removal.processing} onClick={disconnect}>Disconnect locally</Button>}
                {canRevokeGrants && account.status === 'ACTIVE' && <Button type="button" variant="destructive" onClick={requestGrantRevocation}>Request grant revocation</Button>}
            </div>
        </div>
        <ul className="mt-3 flex flex-wrap gap-2">{account.capabilities.map(cap =>
            <li key={cap.capability} title={cap.reason} className="rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground">
                {cap.capability}: {cap.mode}
            </li>)}</ul>
        {removal.errors.account && <p role="alert" className="mt-2 text-sm text-destructive">{removal.errors.account}</p>}
    </li>;
}

export default function SocialAccounts({ organisation, brand, directory, canReviewPrivacy = false,
    canRevokeGrants = false, canVerifyPublishing = false, grantRevocations = [] }) {
    const confirmRevocation = (item) => {
        if (!window.confirm(`Revoke ${item.provider} authorization across ${item.affected_connections} linked connection(s)? This can disconnect other brands in the same organisation.`)) return;
        router.post(route('organisations.grants.revocations.confirm', {
            organisation: organisation.uuid, requestUuid: item.uuid,
        }), {}, { preserveScroll: true });
    };
    return <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-foreground">{brand.name} · Social connections</h2>}>
        <Head title={`${brand.name} · Social accounts`} />
        <main className="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6">
            <Link href={route('organisations.brands.index', {organisation:organisation.uuid})} className="text-sm font-medium text-primary underline">Back to brands</Link>
            {canReviewPrivacy && <Link href={route('organisations.privacy.deletion-reviews.index', { organisation: organisation.uuid })}
                className="text-sm font-medium text-primary underline">Privacy deletion review queue</Link>}
            <div>
                <h1 className="text-2xl font-semibold">Connect social accounts</h1>
                <p className="mt-2 text-sm text-muted-foreground">Every connection belongs to this organisation and is explicitly assigned to {brand.name}. Provider credentials are never shown in the browser.</p>
            </div>
            <section aria-label="Available social providers" className="grid gap-4 sm:grid-cols-2">
                {providers.map(provider => <ConnectionCard key={provider.id} provider={provider} available={directory.providers[provider.id] === true} organisation={organisation} brand={brand} />)}
            </section>
            <section aria-label="Connected accounts">
                <h2 className="mb-4 text-lg font-semibold">Accounts</h2>
                {directory.accounts.length === 0 ? <p className="rounded-xl border border-border bg-muted p-5 text-sm text-muted-foreground">No connected social accounts for this brand yet.</p> :
                    <ul className="space-y-3">{directory.accounts.map(account => <SocialAccountCard key={account.uuid} account={account} organisation={organisation} brand={brand} canRevokeGrants={canRevokeGrants} canVerifyPublishing={canVerifyPublishing}/>)}</ul>}
            </section>
            {canRevokeGrants && <Link href={route('organisations.grants.reconciliations.index', { organisation: organisation.uuid })}
                className="inline-flex text-sm font-medium text-primary underline">Open the grant reconciliation desk</Link>}
            {canRevokeGrants && <section aria-label="Grant revocation requests" className="space-y-3">
                <h2 className="text-lg font-semibold">Grant revocation requests</h2>
                <p className="text-sm text-muted-foreground">Disconnecting a Page locally differs from revoking its entire provider grant. Review the impact count before confirmation. External revocation is disabled by default.</p>
                {grantRevocations.length === 0 ? <p className="text-sm text-muted-foreground">No requests yet.</p> :
                    <ul className="space-y-2">{grantRevocations.map(item => <li key={item.uuid} className="rounded-lg border border-border bg-card p-4 text-sm">
                        <span className="font-medium">{item.provider}</span> · {item.status} · {item.affected_connections} linked connection(s)
                        <p className="text-muted-foreground">{item.reason}</p>
                        {item.status === 'PREPARED' && <Button type="button" variant="destructive" className="mt-2" onClick={() => confirmRevocation(item)}>Confirm entire grant revocation</Button>}
                        {item.status === 'MANUAL_REQUIRED' && <p role="status" className="text-muted-foreground">Manual provider-side action required; no automatic revocation was made.</p>}
                        {item.status === 'UNCERTAIN' && <p role="alert" className="text-destructive">Result uncertain; do not retry automatically. Reconcile with provider.</p>}
                    </li>)}</ul>}
            </section>}
            <p className="text-xs text-muted-foreground">Publishing permission verification performs a read-only provider check. It cannot schedule or publish, expires after 30 minutes, and does not bypass content approval or release switches. Connecting an account does not enable automated publishing. Each network requires separate permissions, provider review, and an approved publishing implementation.</p>
        </main>
    </AuthenticatedLayout>;
}
