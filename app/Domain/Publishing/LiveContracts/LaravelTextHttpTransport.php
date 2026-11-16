<?php

namespace App\Domain\Publishing\LiveContracts;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Deliberately unbound from the container: a guarded caller must explicitly construct it. */
final class LaravelTextHttpTransport implements TextHttpTransportContract
{
    public function send(PreparedProviderRequest $request, string $bearerToken): TextHttpResponse
    {
        ProviderTextEgressPolicy::assertAllowed($request);
        if ($bearerToken === '' || strlen($bearerToken) > 8192 || preg_match('/[\r\n\x00]/', $bearerToken)) {
            throw new \InvalidArgumentException('Invalid credential transport value');
        }
        try {
            // No automatic retries, redirect following, query-string credentials, or exception text logging.
            $reply = Http::withToken($bearerToken)
                ->withHeaders($request->headers)
                ->withOptions(['allow_redirects' => false, 'http_errors' => false])
                ->connectTimeout(5)->timeout(15)->post($request->url, $request->body);
            $data = $reply->json();
            // Keep only a handful of response keys used for receipt classification.
            $body = is_array($data) ? [
                'id' => $data['id'] ?? null,
                'data' => is_array($data['data'] ?? null) ? ['id' => $data['data']['id'] ?? null] : null,
            ] : [];
            $headers = ['x-restli-id' => $reply->header('x-restli-id')];

            return new TextHttpResponse($reply->status(), $body, $headers, true);
        } catch (ConnectionException) {
            // A timeout may occur after the remote platform accepted the POST.
            return new TextHttpResponse(null, [], [], true);
        }
    }
}
