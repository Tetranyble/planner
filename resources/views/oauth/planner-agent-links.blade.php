<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Connected AI clients — Planner</title>
<style>body{font:16px system-ui,sans-serif;max-width:800px;margin:5vh auto;padding:24px}table{width:100%;border-collapse:collapse}td,th{padding:12px;border-bottom:1px solid #ddd;text-align:left}button{padding:8px 16px;cursor:pointer}</style></head>
<body>
<h1>Connected AI clients</h1>
<p>Disconnecting revokes the client's existing access and refresh tokens. It cannot change or delete your published posts.</p>
@if (session('status')) <p role="status">{{ session('status') }}</p> @endif
<table><thead><tr><th>Client ID</th><th>Brand ID</th><th>Delegation</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
@foreach ($links as $link)
<tr><td>{{ $link['client_id'] }}</td><td>{{ $link['brand_id'] }}</td><td>{{ $link['delegation_id'] }}</td>
<td>{{ $link['revoked'] ? 'Revoked' : 'Active' }}</td><td>
@if (! $link['revoked'])
<form method="POST" action="{{ route('planner.oauth.links.destroy', ['linkId' => $link['id']]) }}">
@csrf @method('DELETE') <button type="submit">Disconnect</button></form>
@endif
</td></tr>
@endforeach
</tbody></table>
</body></html>
