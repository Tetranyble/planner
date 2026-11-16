<?php

namespace App\Services\Analytics;

use App\Authorization\BrandPermissionGuard;
use App\Authorization\CurrentOrganisation;
use App\Domain\Analytics\Provider\FacebookPagePostStatistics;
use App\Domain\Analytics\Provider\FacebookPagePostStatsClientContract;
use App\Domain\Analytics\Provider\InstagramMediaStatistics;
use App\Domain\Analytics\Provider\InstagramMediaStatsClientContract;
use App\Domain\Analytics\Provider\TikTokVideoStatistics;
use App\Domain\Analytics\Provider\TikTokVideoStatsClientContract;
use App\Domain\Analytics\Provider\XPostStatistics;
use App\Domain\Analytics\Provider\XPostStatsClientContract;
use App\Domain\Analytics\Provider\YouTubeVideoStatistics;
use App\Domain\Analytics\Provider\YouTubeVideoStatsClientContract;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\ProviderMetricSyncRun;
use App\Models\PublishedPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repository\Contracts\ProviderMetricSyncRepositoryContract;
use App\Services\Publishing\MetaPageTokenResolver;
use Illuminate\Support\Facades\Crypt;

/** Read-only, opt-in five-provider metrics worker. No mutation at provider. */
final class ProcessProviderMetricSyncs
{
    public function __construct(private readonly ProviderMetricSyncRepositoryContract $repo,
        private readonly YouTubeVideoStatsClientContract $client,
        private readonly XPostStatsClientContract $xClient,
        private readonly FacebookPagePostStatsClientContract $facebookClient,
        private readonly TikTokVideoStatsClientContract $tiktokClient,
        private readonly InstagramMediaStatsClientContract $instagramClient,
        private readonly MetaPageTokenResolver $pageTokens,
        private readonly BrandPermissionGuard $guard,
        private readonly CurrentOrganisation $context) {}

    public function process(int $limit = 20): array
    {
        $counts = ['disabled' => ! config('planner.provider_metric_sync_enabled', false),
            'claimed' => 0, 'succeeded' => 0, 'deferred' => 0, 'failed' => 0, 'fenced' => 0];
        if ($counts['disabled']) {
            return $counts;
        }
        $this->repo->recover(50);
        foreach ($this->repo->dueIds($limit) as $id) {
            $lease = bin2hex(random_bytes(24));
            $run = $this->repo->claim((int) $id, $lease);
            if (! $run) {
                continue;
            }
            $counts['claimed']++;
            $code = 'PREFLIGHT_DENIED';
            $obs = null;
            $retry = null;
            try {
                $context = $this->preflight($run);
                // Access token exists only in this call frame, and never appears in a stored result.
                $reply = match ($context['provider']) {
                    'youtube' => $this->client->read($context['token'], $context['post_id']),
                    'x' => $this->xClient->read($context['token'], $context['post_id']),
                    'facebook' => $this->facebookClient->read($context['token'], $context['post_id'], $context['account_id']),
                    'tiktok' => $this->tiktokClient->read($context['token'], $context['post_id']),
                    'instagram' => $this->instagramClient->read($context['token'], $context['post_id'], $context['account_id']),
                };
                $status = $reply['status'];
                if ($status === 200) {
                    $obs = match ($context['provider']) {
                        'youtube' => YouTubeVideoStatistics::normalize($reply['payload'], $context['post_id'], $context['account_id']),
                        'x' => XPostStatistics::normalize($reply['payload'], $context['post_id'], $context['account_id']),
                        'facebook' => FacebookPagePostStatistics::normalize($reply['payload'], $context['post_id'], $context['account_id']),
                        'tiktok' => TikTokVideoStatistics::normalize($reply['payload'], $context['post_id'], $context['account_id']),
                        'instagram' => InstagramMediaStatistics::normalize($reply['payload'], $context['post_id'], $context['account_id']),
                    };
                    $code = 'PROVIDER_API_OBSERVED';
                } elseif ($status === 429 || $status === null || ($status >= 500 && $status <= 599)) {
                    $code = $status === 429 ? 'PROVIDER_RATE_LIMIT' : ($status === null ? 'PROVIDER_NETWORK_FAILURE' : 'PROVIDER_TEMPORARY_FAILURE');
                    $retry = $reply['retry_after'] ?? (int) (60 * pow(2, min(5, (int) $run->attempts)));
                } elseif (in_array($status, [401, 403], true)) {
                    $code = 'PROVIDER_AUTHORIZATION_REQUIRED';
                } elseif ($status === 404) {
                    $code = 'PROVIDER_POST_NOT_FOUND';
                } else {
                    $code = 'PROVIDER_RESPONSE_REJECTED';
                }
            } catch (\DomainException) {
                $code = 'PROVIDER_PREFLIGHT_OR_IDENTITY_FAILED';
            } catch (\Throwable) {
                // Never serialize or log raw provider errors (some contain access tokens).
                $code = 'PROVIDER_SYNC_UNEXPECTED_FAILURE';
                $retry = 300;
            }
            if (! $this->repo->finish((int) $id, $lease, $code, $obs, $retry)) {
                $counts['fenced']++;

                continue;
            }
            $counts[$obs !== null ? 'succeeded' : ($retry !== null && (int) $run->attempts < 3 ? 'deferred' : 'failed')]++;
        }

        return $counts;
    }

    private function preflight(ProviderMetricSyncRun $run): array
    {
        $org = Organisation::query()->whereKey($run->organisation_id)->first();
        $brand = Brand::query()->where('organisation_id', $run->organisation_id)
            ->whereKey($run->brand_id)->first();
        $actor = User::query()->whereKey($run->requested_by_user_id)->first();
        if (! $org || ! $brand || ! $actor || ! in_array($run->provider, ['youtube', 'x', 'facebook', 'tiktok', 'instagram'], true)
            || ($run->provider === 'x' && ! config('planner.x_metric_sync_enabled', false))
            || ($run->provider === 'facebook' && ! config('planner.facebook_metric_sync_enabled', false))
            || ($run->provider === 'tiktok' && ! config('planner.tiktok_metric_sync_enabled', false))
            || ($run->provider === 'instagram' && ! config('planner.instagram_metric_sync_enabled', false))) {
            throw new \DomainException('Metric sync actor or provider no longer available.');
        }

        return $this->context->within($actor, (int) $org->id, function () use ($org, $brand, $actor, $run) {
            if (! $this->guard->allows($actor, $brand, 'approval_request.index')) {
                throw new \DomainException('Metric sync read permission revoked.');
            }
            $provider = $run->provider;
            $post = PublishedPost::query()->where('organisation_id', $org->id)
                ->where('brand_id', $brand->id)->whereKey($run->published_post_id)
                ->where('provider', $provider)->where('remote_status', 'PUBLISHED')->first();
            $validPost = $post && ($provider === 'youtube'
                ? (bool) preg_match('/^[A-Za-z0-9_-]{11}$/D', (string) $post->external_post_id)
                : ($provider === 'facebook'
                    ? FacebookPagePostStatistics::validPostId((string) $post->external_post_id,
                        (string) SocialAccount::query()->where('organisation_id', $org->id)
                            ->where('brand_id', $brand->id)->whereKey($post->social_account_id)->value('external_account_id'))
                    : ($provider === 'tiktok'
                        ? TikTokVideoStatistics::validVideoId((string) $post->external_post_id)
                        : ($provider === 'instagram'
                            ? InstagramMediaStatistics::numericId((string) $post->external_post_id)
                            : XPostStatistics::snowflake((string) $post->external_post_id)))));
            if (! $validPost) {
                throw new \DomainException('Confirmed provider post no longer eligible.');
            }
            $account = SocialAccount::query()->where('organisation_id', $org->id)
                ->where('brand_id', $brand->id)->where('provider', $provider)->where('status', 'ACTIVE')
                ->whereKey($post->social_account_id)->first();
            $validAccount = $account && ($provider === 'youtube'
                ? (bool) preg_match('/^UC[A-Za-z0-9_-]{22}$/D', (string) $account->external_account_id)
                : ($provider === 'facebook'
                    ? (bool) preg_match('/^[1-9][0-9]{2,29}$/D', (string) $account->external_account_id)
                    : ($provider === 'tiktok'
                        ? ((string) $account->external_account_id !== '' && strlen((string) $account->external_account_id) <= 160)
                        : ($provider === 'instagram'
                            ? InstagramMediaStatistics::numericId((string) $account->external_account_id)
                            : XPostStatistics::snowflake((string) $account->external_account_id)))));
            if (! $validAccount) {
                throw new \DomainException('Connected provider identity is missing.');
            }
            $connection = ProviderConnection::query()->where('organisation_id', $org->id)
                ->whereKey($account->provider_connection_id)->where('provider', $provider)
                ->where('status', 'CONNECTED')->first();
            if (! $connection || ! $connection->token_expires_at || $connection->token_expires_at->lte(now()->addMinutes(3))) {
                throw new \DomainException('Provider credential expired.');
            }
            $readScopes = $provider === 'youtube' ? [
                'https://www.googleapis.com/auth/youtube.readonly',
                'https://www.googleapis.com/auth/youtube',
                'https://www.googleapis.com/auth/youtube.force-ssl'] : ($provider === 'facebook'
                    ? ['pages_read_engagement'] : ($provider === 'tiktok' ? ['video.list'] :
                        ($provider === 'instagram' ? ['instagram_manage_insights'] : ['tweet.read'])));
            if (array_intersect($connection->scopes ?? [], $readScopes) === []) {
                throw new \DomainException('Required provider read scope was not granted.');
            }
            $credential = ProviderConnectionCredential::query()->where('organisation_id', $org->id)
                ->where('provider_connection_id', $connection->id)->first();
            // The TikTok query endpoint binds video ownership to the authenticated grant;
            // ensure that grant's current actor matches this selected brand account.
            if ($provider === 'tiktok' && (! is_string($connection->external_actor_id)
                || ! hash_equals((string) $account->external_account_id, $connection->external_actor_id))) {
                throw new \DomainException('TikTok grant actor differs from selected account.');
            }
            if (! $credential?->access_token_ciphertext) {
                throw new \DomainException('Provider access token unavailable.');
            }
            $token = Crypt::decryptString($credential->access_token_ciphertext);
            if (! is_string($token) || $token === '' || strlen($token) > 8192) {
                throw new \DomainException('Invalid provider credential.');
            }
            // Meta selected Page token comes from a currently permitted user grant, never stored.
            if ($provider === 'facebook') {
                $token = $this->pageTokens->resolve($token,(string) $account->external_account_id,true);
            }

            return ['provider' => $provider, 'post_id' => (string) $post->external_post_id, 'account_id' => (string) $account->external_account_id, 'token' => $token];
        });
    }
}
