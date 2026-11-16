<?php

namespace App\Social\Providers;

use App\Social\Data\MetaTokenInspection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Fixed-host Graph /debug_token. Credentials are sent only over TLS. */
final class MetaAccessTokenInspector
{
    public function inspect(string $token): MetaTokenInspection
    {
        $appId = (string) config('social_integrations.providers.facebook.client_id');
        $secret = (string) config('social_integrations.providers.facebook.client_secret');
        if ($appId === '' || $secret === '' || $token === '') {
            throw new RuntimeException('META_INSPECTION_NOT_CONFIGURED');
        }
        $version = (string) config('social_integrations.meta_graph_version', 'v27.0');
        if (! preg_match('/^v[0-9]+\.[0-9]+$/D', $version)) {
            throw new RuntimeException('META_GRAPH_VERSION_INVALID');
        }
        $response = Http::withToken($appId.'|'.$secret)->acceptJson()->timeout(15)
            ->get('https://graph.facebook.com/'.$version.'/debug_token', ['input_token' => $token]);
        if (! $response->successful()) {
            throw new RuntimeException('META_INSPECTION_UNAVAILABLE');
        }
        $data = $response->json('data');
        if (! is_array($data) || ! is_bool($data['is_valid'] ?? null)
            || ! is_string($data['app_id'] ?? null)) {
            throw new RuntimeException('META_INSPECTION_INVALID_RESPONSE');
        }
        $scopes = array_values(array_unique(array_filter($data['scopes'] ?? [], 'is_string')));
        $targets = [];
        foreach (($data['granular_scopes'] ?? []) as $entry) {
            if (! is_array($entry) || ! is_string($entry['scope'] ?? null)) {
                continue;
            }
            if (array_key_exists('target_ids', $entry) && is_array($entry['target_ids'])) {
                $targets[$entry['scope']] = array_map('strval', $entry['target_ids']);
            }
        }

        return new MetaTokenInspection(
            $data['is_valid'] && hash_equals($appId, $data['app_id']),
            $data['app_id'],
            (string) ($data['user_id'] ?? ''),
            $scopes,
            $targets,
            isset($data['expires_at']) && is_numeric($data['expires_at']) && (int) $data['expires_at'] > 0 ? (int) $data['expires_at'] : null,
            isset($data['data_access_expires_at']) && is_numeric($data['data_access_expires_at']) && (int) $data['data_access_expires_at'] > 0 ? (int) $data['data_access_expires_at'] : null,
        );
    }
}
