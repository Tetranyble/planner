<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Approve AI access — Planner</title>
    <style>body{font:16px system-ui,sans-serif;max-width:590px;margin:5vh auto;padding:24px;color:#181818}select,button{font:inherit;padding:12px;width:100%;margin:10px 0}button{cursor:pointer}main{border:1px solid #ddd;border-radius:12px;padding:24px}.muted{color:#666;font-size:14px}.danger{background:#fff;border:1px solid #aaa}</style>
</head>
<body>
<main>
    <h1>Connect Planner to an AI client</h1>
    <p><strong>{{ $client->name }}</strong> is requesting access through Planner's MCP interface.</p>
    <p class="muted">Signed in as {{ $user->email }}. The client will be limited to one existing Planner agent delegation. It cannot approve content itself. Publishing permissions remain subject to explicit owner controls.</p>
    @if ($errors->any()) <p role="alert">{{ $errors->first() }}</p> @endif
    @if (count($delegations))
        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <label for="delegation">Select the brand and delegation to authorize</label>
            <select id="delegation" name="delegation_id" required>
                @foreach ($delegations as $delegation)
                    <option value="{{ $delegation['id'] }}">{{ $delegation['brand'] }} — {{ $delegation['name'] }} ({{ $delegation['mode'] }})</option>
                @endforeach
            </select>
            <p class="muted">You can revoke this connection and all related OAuth tokens at any time from Planner.</p>
            <button type="submit">Approve this connection</button>
        </form>
    @else
        <p role="alert">No active, permitted agent delegation is available. Create one for your brand in Planner, then reconnect.</p>
    @endif
    <form method="POST" action="{{ route('passport.authorizations.deny') }}">
        @csrf
        @method('DELETE')
        <input type="hidden" name="client_id" value="{{ $client->id }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
        <button class="danger" type="submit">Deny access</button>
    </form>
</main>
</body>
</html>
