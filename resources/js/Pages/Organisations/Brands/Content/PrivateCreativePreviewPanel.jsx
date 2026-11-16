import { useEffect, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';

/** Review and hand off one operator-generated PNG. Storage owns all bytes after upload. */
export default function PrivateCreativePreviewPanel({ organisationUuid, brandUuid, canCreate = false, available = false, autoStorageAvailable = false }) {
    const [brief, setBrief] = useState('');
    const [size, setSize] = useState('1024x1024');
    const [autoStore, setAutoStore] = useState(false);
    const [previewUrl, setPreviewUrl] = useState(null);
    const [digest, setDigest] = useState('');
    const [handoffUuid, setHandoffUuid] = useState('');
    const [expiresAt, setExpiresAt] = useState('');
    const [storageMediaId, setStorageMediaId] = useState('');
    const [candidates, setCandidates] = useState([]);
    const [cursor, setCursor] = useState(null);
    const [humanReviewed, setHumanReviewed] = useState(false);
    const [linkedAsset, setLinkedAsset] = useState('');
    const [busy, setBusy] = useState(false);
    const [checking, setChecking] = useState(false);
    const [linking, setLinking] = useState(false);
    const [message, setMessage] = useState('');

    useEffect(() => () => { if (previewUrl) URL.revokeObjectURL(previewUrl); }, [previewUrl]);
    if (!canCreate) return null;
    const scope = `/organisations/${organisationUuid}/brands/${brandUuid}/creative`;

    const generate = async event => {
        event.preventDefault(); setBusy(true); setMessage(''); setDigest(''); setPreviewUrl(null);
        setHandoffUuid(''); setExpiresAt(''); setStorageMediaId(''); setCandidates([]);
        setHumanReviewed(false); setLinkedAsset('');
        try {
            const response = await axios.post(`${scope}/private-preview`,
                { prompt: brief.trim(), size, auto_store: autoStore }, { responseType: 'blob', timeout: 125000 });
            if (!response.headers['content-type']?.startsWith('image/png') ||
                !/^[a-f0-9]{64}$/i.test(response.headers['x-generated-image-sha256'] ?? '') ||
                !/^[0-9a-f-]{36}$/i.test(response.headers['x-creative-handoff'] ?? '')) {
                throw new Error('Unexpected image handoff metadata');
            }
            setPreviewUrl(URL.createObjectURL(response.data));
            setDigest(response.headers['x-generated-image-sha256']);
            setHandoffUuid(response.headers['x-creative-handoff']);
            setExpiresAt(response.headers['x-creative-handoff-expires-at'] ?? '');
            const ingestState = response.headers['x-creative-storage-ingest-state'];
            if (ingestState === 'PENDING_STORAGE_REVIEW' && /^[1-9]\d*$/.test(response.headers['x-creative-storage-media-id'] ?? '')) {
                setStorageMediaId(response.headers['x-creative-storage-media-id']);
            }
            setMessage(response.headers['x-creative-storage-ingest-state'] === 'PENDING_STORAGE_REVIEW'
                ? 'Unpublished image uploaded using Tetranyble Storage. Review it and wait for malware scanning before linking; no publication approval was granted.'
                : response.headers['x-creative-storage-ingest-state'] === 'FAILED'
                    ? 'Image generated, but canonical upload failed. Download the PNG and use the normal Storage uploader; do not regenerate just to retry an upload.'
                    : 'Unpublished visual generated. Download, inspect it, and upload it through canonical Storage before attaching to a draft.');
        } catch {
            setMessage('Private creative generation could not be completed. Check release settings and quota. Do not automatically retry a timed-out request.');
        } finally { setBusy(false); }
    };

    const browseVerifiedUploads = async (beforeId = 0) => {
        setChecking(true); setMessage('');
        try {
            const response = await axios.get(`/api/v1/organisations/${organisationUuid}/brands/${brandUuid}/content/media-assets/storage-candidates`,
                { params: beforeId ? { before_id: beforeId } : {} });
            const result = response.data?.data ?? { items: [], next_before_id: null };
            const rows = result.items ?? [];
            const matching = rows.filter(file => file.safe_to_link && file.mime_type === 'image/png' &&
                file.sha256?.toLowerCase() === digest.toLowerCase());
            setCandidates(beforeId ? previous => [...previous, ...matching] : matching);
            setCursor(result.next_before_id ?? null);
            if (!matching.length) setMessage('No matching, scanned PNG on this Storage page. Upload the downloaded image using Tetranyble Storage and check again after it is approved.');
        } catch { setMessage('Could not browse package-owned uploads. You may still enter the approved Storage media ID.'); }
        finally { setChecking(false); }
    };

    const linkUploadedImage = async event => {
        event.preventDefault();
        if (!humanReviewed || !handoffUuid || !/^[1-9]\d*$/.test(String(storageMediaId))) return;
        setLinking(true); setMessage('');
        try {
            const response = await axios.post(`${scope}/handoffs/${handoffUuid}/link`,
                { storage_media_id: Number(storageMediaId), reviewed: humanReviewed });
            const assetUuid = response.data?.data?.media_asset_uuid;
            if (!assetUuid) throw new Error('Missing media linkage receipt');
            setLinkedAsset(assetUuid);
            setMessage('Verified original linked to Content Studio as an unapproved media asset. Select it in a draft; approval and publication remain separate.');
            router.reload({ only: ['studio'] });
        } catch (error) {
            setMessage(error?.response?.status === 409
                ? 'This image handoff has expired or was already claimed for a different file. Generate a new image.'
                : 'Linking was refused. Confirm exact image bytes, uploader identity, scan result and workspace permission.');
        } finally { setLinking(false); }
    };

    return <section className="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-card" aria-label="Private AI image preview">
        <h2 className="text-lg font-semibold">Creative Studio · Reviewed image handoff</h2>
        <p className="mt-1 text-xs text-muted-foreground">Generate privately, review, and optionally send the unchanged PNG to canonical Tetranyble Storage. Only a verified upload matching the signed-in operator, current brand and exact SHA-256 can be linked. No automatic publishing or approval.</p>
        <p className="mt-1 text-xs text-muted-foreground">Release status: {available ? 'Enabled' : 'Disabled (default)'}</p>
        <form onSubmit={generate} className="mt-3 grid gap-3 sm:grid-cols-2">
            <label className="text-sm sm:col-span-2">Creative brief<textarea rows={3} required minLength={20} maxLength={1400} value={brief} onChange={event => setBrief(event.target.value)} placeholder="Describe composition, brand colors and intended audience" className="mt-1 w-full rounded border border-input bg-background p-2" /></label>
            <label className="text-sm">Canvas size<select value={size} onChange={event=>setSize(event.target.value)} className="mt-1 w-full rounded border border-input bg-background p-2">
                <option value="1024x1024">Square · 1024 × 1024</option>
                <option value="1536x1024">Landscape · 1536 × 1024</option>
                <option value="1024x1536">Portrait · 1024 × 1536</option>
            </select></label>
            {autoStorageAvailable && <label className="flex items-center gap-2 text-xs sm:col-span-2"><input type="checkbox" checked={autoStore} onChange={event => setAutoStore(event.target.checked)} /> Upload generated PNG into canonical Storage automatically (requires verified server integration; human review still required)</label>}
            <div className="flex items-end"><Button type="submit" disabled={!available || busy || linking}>{busy ? 'Generating private image…' : 'Generate private image'}</Button></div>
        </form>
        {message && <p role="status" className="mt-3 text-sm text-muted-foreground">{message}</p>}
        {previewUrl && <div className="mt-4 space-y-2">
            <img src={previewUrl} alt="Unpublished AI-generated marketing visual awaiting operator inspection" className="max-h-80 max-w-full rounded-lg border border-border object-contain" />
            <a href={previewUrl} download="planner-private-creative.png" className="inline-block rounded border border-input px-3 py-2 text-sm font-medium text-primary underline">Download original PNG</a>
            <p className="break-all font-mono text-xs text-muted-foreground">SHA-256: {digest}</p>
            <p className="text-xs text-muted-foreground">One-time handoff expires {expiresAt || 'soon'}; do not edit or recompress the original PNG before upload. No image bytes are saved to Planner's application database.</p>
        </div>}
        {previewUrl && handoffUuid && !linkedAsset && <form className="mt-4 space-y-3 border-t border-border pt-4" onSubmit={linkUploadedImage}>
            <h3 className="font-medium">Link an independently verified upload</h3>
            <p className="text-xs text-muted-foreground">First upload the PNG using the official Storage workflow. Wait until scanning and verification finish. This step registers a reference, never uploads, approves, schedules or publishes content.</p>
            <Button type="button" size="sm" variant="outline" disabled={checking} onClick={() => browseVerifiedUploads(0)}>{checking ? 'Checking…' : 'Find matching verified uploads'}</Button>
            {candidates.length > 0 && <ul className="space-y-1" aria-label="Matching verified uploaded PNGs">
                {candidates.map(file => <li key={file.storage_media_id} className="flex items-center justify-between gap-2 rounded border border-border px-3 py-2 text-xs">
                    <span className="truncate">{file.file_name} · {file.byte_size} bytes</span>
                    <Button size="sm" type="button" variant="outline" onClick={() => setStorageMediaId(String(file.storage_media_id))}>Select</Button>
                </li>)}
            </ul>}
            {!!cursor && <Button type="button" size="sm" variant="outline" disabled={checking} onClick={() => browseVerifiedUploads(cursor)}>Search older uploads</Button>}
            <label className="block text-sm">Verified Storage media ID<input className="mt-1 w-full max-w-xs rounded border border-input bg-background p-2" type="number" min="1" required value={storageMediaId} onChange={event => setStorageMediaId(event.target.value)}/></label>
            <label className="flex items-start gap-2 text-sm"><input type="checkbox" checked={humanReviewed} onChange={event => setHumanReviewed(event.target.checked)} /> I have inspected the image for brand accuracy, claims, and content rights; I understand further approval is required.</label>
            <Button type="submit" disabled={!available || !humanReviewed || !storageMediaId || linking}>{linking ? 'Verifying Storage…' : 'Link verified media to Content Studio'}</Button>
        </form>}
        {linkedAsset && <p className="mt-3 break-all text-sm" role="status">Content Studio media asset: <span className="font-mono">{linkedAsset}</span> · still requires draft and approval</p>}
    </section>;
}
