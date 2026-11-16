import OperationalSafetyPanel from './OperationalSafetyPanel';
import IntegrationAccessPanel from './IntegrationAccessPanel';
import AgentDelegationPanel from './AgentDelegationPanel';
import PrivateCreativePreviewPanel from './PrivateCreativePreviewPanel';
import ReportAutomationsPanel from './ReportAutomationsPanel';
import SocialMetricsPanel from './SocialMetricsPanel';
import AiCampaignProposalsPanel from './AiCampaignProposalsPanel';
import AiSuggestionsPanel from './AiSuggestionsPanel';
import OperationsDashboardPanel from './OperationsDashboardPanel';
import RiskDeclarations from './RiskDeclarations';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';

const field = 'mt-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-2 focus-visible:outline-ring';
const panel = 'rounded-xl border border-border bg-card p-5 text-card-foreground shadow-card';

export default function Index({ organisation, brand, studio, can }) {
    const root = `/organisations/${organisation.uuid}/brands/${brand.uuid}/content`;
    const draft = useForm({ title: '', content_kind: 'TEXT', copy_text: '', media_uuids: [], ai_generated: null, includes_sensitive_claim: null });
    const asset = useForm({ storage_media_id: '' });
    const [filter, setFilter] = useState('');
    const [storageCandidates, setStorageCandidates] = useState([]);
    const [storageCursor, setStorageCursor] = useState(null);
    const [storageMessage, setStorageMessage] = useState('');
    const [loadingStorage, setLoadingStorage] = useState(false);
    const browseStorage = async (beforeId = 0) => {
        setLoadingStorage(true); setStorageMessage('');
        try {
            const endpoint = `/api/v1/organisations/${organisation.uuid}/brands/${brand.uuid}/content/media-assets/storage-candidates`;
            const response = await axios.get(endpoint, { params: beforeId ? { before_id: beforeId } : {} });
            const results = response.data?.data ?? { items: [], next_before_id: null };
            setStorageCandidates(results.items ?? []);
            setStorageCursor(results.next_before_id ?? null);
            if (!(results.items ?? []).length) setStorageMessage('No scanned, ready media found among recent uploads. Upload through Tetranyble Storage first.');
        } catch { setStorageMessage('Cannot browse verified Storage media with your current permission.'); }
        finally { setLoadingStorage(false); }
    };
    const items = studio.items?.data ?? [];
    const visibleItems = items.filter(item => `${item.title} ${item.kind}`.toLowerCase().includes(filter.toLowerCase()));
    return <AuthenticatedLayout header={<div className="flex flex-wrap items-center justify-between gap-3"><h1 className="text-xl font-semibold">{brand.name} · Content studio</h1><Link className="text-sm text-primary underline-offset-4 hover:underline" href={`/organisations/${organisation.uuid}/brands`}>Back to brands</Link></div>}>
        <Head title={`Content studio · ${brand.name}`} />
        <div className="mx-auto grid max-w-7xl gap-6 px-4 py-7 lg:grid-cols-[minmax(0,1fr)_22rem] sm:px-6">
            <div className="lg:col-span-2"><AiSuggestionsPanel organisationUuid={organisation.uuid} brandUuid={brand.uuid} canCreate={can.create} /></div>
            <div className="lg:col-span-2"><AiCampaignProposalsPanel organisationUuid={organisation.uuid} brandUuid={brand.uuid} canCreate={can.create} canReview={can.reviewCampaign} /></div>
            <div className="lg:col-span-2"><IntegrationAccessPanel organisationUuid={organisation.uuid} brandUuid={brand.uuid} canManage={Boolean(can.reviewCampaign)} /></div>
            <div className="lg:col-span-2"><PrivateCreativePreviewPanel organisationUuid={organisation.uuid} brandUuid={brand.uuid} canCreate={Boolean(can.media)} available={Boolean(can.creativeImageEnabled)} autoStorageAvailable={Boolean(can.creativeAutoStorageEnabled)} /></div>
            <div className="lg:col-span-2"><AgentDelegationPanel organisationUuid={organisation.uuid} brandUuid={brand.uuid} canManage={Boolean(can.reviewCampaign)} accounts={studio.accounts ?? []} /></div>
            <div className="lg:col-span-2"><OperationalSafetyPanel organisationUuid={organisation.uuid} brandUuid={brand.uuid} /></div>
            <div className="lg:col-span-2"><OperationsDashboardPanel organisationUuid={organisation.uuid} brandUuid={brand.uuid} /></div>
            <div className="lg:col-span-2"><ReportAutomationsPanel organisationUuid={organisation.uuid} brandUuid={brand.uuid} canManage={Boolean(can.reviewCampaign)} /></div>
            <div className="lg:col-span-2"><SocialMetricsPanel organisationUuid={organisation.uuid} brandUuid={brand.uuid} canSubmit={Boolean(can.reviewCampaign)} /></div>
            <section className={panel} aria-labelledby="drafts-heading">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 id="drafts-heading" className="text-lg font-semibold">Draft content</h2><input className={field+' max-w-64'} aria-label="Filter content" placeholder="Find a draft" value={filter} onChange={event => setFilter(event.target.value)} /></div>
                <p className="mb-4 text-sm text-muted-foreground">Drafts and versions stay private. No social posts are scheduled from this screen.</p>
                {visibleItems.length ? <ul className="divide-y divide-border">{visibleItems.map(item => <li key={item.uuid} className="py-4"><Link className="font-medium text-primary hover:underline" href={`${root}/${item.uuid}`}>{item.title}</Link><div className="mt-1 text-xs text-muted-foreground">{item.kind} · {item.state}</div></li>)}</ul> : <p className="py-8 text-sm text-muted-foreground">No matching drafts yet.</p>}
                <nav className="mt-5 flex items-center justify-between gap-3 text-sm" aria-label="Content pages">
                    {studio.items?.current_page>1 ? <Link className="text-primary hover:underline" href={`${root}?page=${studio.items.current_page-1}`}>Previous</Link> : <span className="text-muted-foreground">Previous</span>}
                    <span className="text-muted-foreground">Page {studio.items?.current_page ?? 1} of {studio.items?.last_page ?? 1}</span>
                    {studio.items?.current_page<studio.items?.last_page ? <Link className="text-primary hover:underline" href={`${root}?page=${studio.items.current_page+1}`}>Next</Link> : <span className="text-muted-foreground">Next</span>}
                </nav>
            </section>
            <div className="space-y-6">
                {can.create && <section className={panel} aria-label="Create draft">
                    <h2 className="text-lg font-semibold">New content draft</h2>
                    <form className="mt-4 space-y-3" onSubmit={event => {event.preventDefault();draft.post(root, {preserveScroll: true, onSuccess: () => draft.reset()});}}>
                        <label className="block text-sm">Title<input className={field} value={draft.data.title} onChange={e => draft.setData('title',e.target.value)} maxLength={180} required /></label>
                        {draft.errors.title && <p className="text-xs text-destructive" role="alert">{draft.errors.title}</p>}
                        <label className="block text-sm">Content type<select className={field} value={draft.data.content_kind} onChange={e => draft.setData('content_kind',e.target.value)}>{['TEXT','IMAGE','CAROUSEL','VIDEO','SHORT','ARTICLE'].map(kind => <option key={kind}>{kind}</option>)}</select></label>
                        <label className="block text-sm">Copy<textarea className={field} rows={5} value={draft.data.copy_text} onChange={e => draft.setData('copy_text',e.target.value)} required maxLength={50000} /></label>
                        <RiskDeclarations form={draft} />
                        {draft.errors.copy_text && <p className="text-xs text-destructive" role="alert">{draft.errors.copy_text}</p>}
                        {studio.media.length > 0 && <fieldset className="space-y-1"><legend className="text-sm">Attached media (up to 12)</legend>{studio.media.map(media => <label key={media.uuid} className="flex items-center gap-2 text-sm"><input type="checkbox" checked={draft.data.media_uuids.includes(media.uuid)} onChange={event => draft.setData(previous => ({ ...previous, media_uuids: event.target.checked ? [...previous.media_uuids,media.uuid] : previous.media_uuids.filter(id => id !== media.uuid), ai_generated: event.target.checked && media.ai_generated_source ? true : previous.ai_generated }))} />{media.file_name}{media.ai_generated_source ? ' · AI-generated image' : ''}</label>)}</fieldset>}
                        <Button disabled={draft.processing} type="submit">{draft.processing ? 'Saving…' : 'Create draft'}</Button>
                    </form>
                </section>}
                {can.media && <section className={panel} aria-label="Link Storage media">
                    <h2 className="text-lg font-semibold">Link existing media</h2><p className="mt-2 text-sm text-muted-foreground">Upload and scan files through Tetranyble Storage first. Browse verified files you uploaded in this organisation, or enter an ID directly.</p>
                    <div className="mt-3 space-y-2" aria-label="Verified Storage media browser">
                        <Button type="button" variant="outline" size="sm" disabled={loadingStorage} onClick={() => browseStorage(0)}>{loadingStorage ? 'Checking Storage…' : 'Browse my verified uploads'}</Button>
                        {storageMessage && <p className="text-xs text-muted-foreground" role="status">{storageMessage}</p>}
                        <ul className="max-h-48 space-y-1 overflow-y-auto">
                            {storageCandidates.map(media => <li key={media.storage_media_id} className="flex items-center justify-between gap-2 rounded border border-border p-2 text-xs">
                                <span className="min-w-0 truncate" title={media.file_name}>{media.file_name} · {media.mime_type}</span>
                                <Button type="button" size="sm" variant="outline" onClick={() => asset.setData('storage_media_id',String(media.storage_media_id))}>Select</Button>
                            </li>)}
                        </ul>
                        {!!storageCursor && <Button type="button" size="sm" variant="outline" disabled={loadingStorage} onClick={() => browseStorage(storageCursor)}>Older verified uploads</Button>}
                    </div>
                    <form className="mt-3 space-y-3" onSubmit={event => {event.preventDefault(); asset.transform(value => ({storage_media_id:Number(value.storage_media_id)})).post(`${root}/media-assets`,{preserveScroll:true,onSuccess:()=>asset.reset()});}}>
                        <label className="block text-sm">Storage media ID<input className={field} type="number" min="1" required value={asset.data.storage_media_id} onChange={e => asset.setData('storage_media_id',e.target.value)}/></label>
                        {asset.errors.storage_media_id && <p role="alert" className="text-xs text-destructive">{asset.errors.storage_media_id}</p>}
                        <Button type="submit" disabled={asset.processing}>Link verified media</Button>
                    </form>
                </section>}
            </div>
        </div>
    </AuthenticatedLayout>;
}
