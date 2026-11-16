<?php

namespace App\Services\Publishing;

use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Page access token is resolved on demand, never cached, logged, persisted or returned to the browser. */
final class MetaPageTokenResolver
{
    public function resolve(string $userAccessToken, string $expectedPageId, bool $forInsights = false): string
    {
        if (! preg_match('/^[0-9]{3,30}$/D', $expectedPageId)) {
            throw new ConflictHttpException('Invalid selected Page identity.');
        }
        if ($userAccessToken === '' || strlen($userAccessToken) > 8192 || preg_match('/[\r\n\x00]/', $userAccessToken)) {
            throw new ConflictHttpException('Invalid Meta grant.');
        }
        $version = (string) config('social_integrations.meta_graph_version', 'v27.0');
        if (! preg_match('/^v[0-9]{1,2}\.[0-9]{1,2}$/D', $version)) {
            throw new ConflictHttpException('Invalid Meta Graph version.');
        }
        $after = null;
        $seen = [];
        for ($page = 0; $page < 10; $page++) {
            $query = ['fields' => 'id,access_token,tasks', 'limit' => 100];
            if ($after !== null) {
                $query['after'] = $after;
            }
            $reply = Http::withToken($userAccessToken)->acceptJson()
                ->withOptions(['allow_redirects' => false, 'http_errors' => false])
                ->connectTimeout(5)->timeout(12)
                ->get('https://graph.facebook.com/'.$version.'/me/accounts', $query);
            if ($reply->status() !== 200) {
                throw new ConflictHttpException('Meta Page authorization could not be verified.');
            }
            $body = $reply->json();
            if (! is_array($body) || ! is_array($body['data'] ?? null)) {
                throw new ConflictHttpException('Meta Page listing is invalid.');
            }
            foreach ($body['data'] as $item) {
                if (! is_array($item) || (string) ($item['id'] ?? '') !== $expectedPageId) {
                    continue;
                }
                $tasks = $item['tasks'] ?? [];
                $token = $item['access_token'] ?? null;
                if (! is_array($tasks) || array_intersect($forInsights ? ['ANALYZE', 'MANAGE'] : ['CREATE_CONTENT', 'MANAGE'], $tasks) === []
                    || ! is_string($token) || $token === '' || strlen($token) > 8192 || preg_match('/[\r\n\x00]/', $token)) {
                    throw new ConflictHttpException($forInsights ? 'Selected Page has no verified insights access.' : 'Selected Page has no verified content-creation access.');
                }

                return $token;
            }
            $cursor = $body['paging']['cursors']['after'] ?? null;
            if (empty($body['paging']['next'])) {
                break;
            }
            if (! is_string($cursor) || $cursor === '' || strlen($cursor) > 512 || isset($seen[$cursor])) {
                throw new ConflictHttpException('Meta Page pagination is invalid.');
            }
            $seen[$cursor] = true;
            $after = $cursor;
        }
        throw new ConflictHttpException('Selected Facebook Page is not authorized on this grant.');
    }
}
