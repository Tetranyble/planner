import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
export default function Approvals({organisation,brand,itemUuid,approvals=[]}) {
 const base=`/organisations/${organisation.uuid}/brands/${brand.uuid}/content/${itemUuid}`;
 return <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Content approvals</h1>}>
  <Head title="Content approvals · Planner"/>
  <main className="mx-auto max-w-4xl px-4 py-8"><Link href={base} className="text-primary hover:underline">Back to content draft</Link>
   <ul className="mt-5 space-y-3">{approvals.map(a=><li className="rounded-xl border border-border bg-card p-4" key={a.id}>
    <p className="font-semibold">Review #{a.id} · {a.status}</p>
    <p className="text-sm text-muted-foreground">Version {a.version_number} · {a.approvals_received}/{a.required_approvals} approvals</p>
    <p className="break-all font-mono text-xs text-muted-foreground">{a.payload_hash}</p>
   </li>)}</ul>{!approvals.length&&<p className="mt-5 text-sm text-muted-foreground">No review history for this content.</p>}
  </main>
 </AuthenticatedLayout>;
}
