<?php

namespace App\Services\Publishing;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Publishing\LiveContracts\PublishingScopePolicy;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\SocialPublishCapabilityRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** An explicit owner/manager read-only provider probe; NOT blanket publishing permission. */
final class VerifySocialPublishingCapability
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly SocialPublishCapabilityRepositoryContract $repository,
        private readonly AuditEventRepositoryContract $audit,
        private readonly MetaPageTokenResolver $pages,
    ) {}

    public function verify(User $actor, Organisation $organisation, Brand $brand, string $accountUuid): array
    {
        if ((int) $brand->organisation_id !== (int) $organisation->id
            || ! $this->guard->allows($actor, $brand, 'social-account.connect')
            || ! $this->guard->allows($actor, $brand, 'approval_request.update')) {
            throw new AuthorizationException('Publishing access verification requires manager permissions.');
        }
        $ctx = $this->repository->context($organisation, $brand, $accountUuid);
        if (! PublishingScopePolicy::permitted($ctx['provider'], $ctx['account_kind'], $ctx['scopes'])) {
            throw new ConflictHttpException('Provider publishing product, identity or OAuth consent is not approved; reconnect after enabling entitlements.');
        }
        // Fixed, read-only provider endpoints; fail closed if the provider cannot be verified.
        $verified = match ($ctx['provider']) {
            'facebook' => $this->facebook($ctx),
            'x' => $this->x($ctx),
            'linkedin' => $this->linkedin($ctx),
            default => false,
        };

        // No remote network I/O in the write transaction. Recheck the original token digest on commit.
        return DB::transaction(function () use ($actor, $organisation, $brand, $ctx, $verified) {
            $status = $this->repository->record($organisation, $brand, $ctx['id'],
                $ctx['credential_digest'], $verified ? 'AUTOMATIC' : 'UNAVAILABLE',
                $verified ? 'PROVIDER_ACCOUNT_VERIFIED' : 'PROVIDER_IDENTITY_MISMATCH');
            $this->audit->record($organisation, $actor, 'social.publish-capability.checked',
                'social_account', $status['account_uuid'], $brand,
                ['provider' => $status['provider'], 'mode' => $status['mode'], 'reason_code' => $status['reason_code']]);

            return $status;
        }, 3);
    }

    private function facebook(array $ctx): bool
    {
        // The Page token never leaves this process and is never saved to a database.
        $this->pages->resolve($ctx['token'], $ctx['external_account_id']);

        return true;
    }

    private function x(array $ctx): bool
    {
        if (! preg_match('/^[0-9]{3,30}$/D', $ctx['external_account_id'])) {
            return false;
        }
        $reply = Http::withToken($ctx['token'])->acceptJson()->withOptions(['allow_redirects' => false, 'http_errors' => false])
            ->connectTimeout(5)->timeout(12)->get('https://api.x.com/2/users/me');
        $body = $reply->json();

        return $reply->status() === 200 && is_array($body)
            && (string) ($body['data']['id'] ?? '') === $ctx['external_account_id'];
    }

    private function linkedin(array $ctx): bool
    {
        // Verify the exact OIDC subject; LinkedIn Person URN may contain opaque non-numeric IDs.
        if (! preg_match('/^[a-zA-Z0-9_-]{2,100}$/D', $ctx['external_account_id'])) {
            return false;
        }
        $reply = Http::withToken($ctx['token'])->acceptJson()->withOptions(['allow_redirects' => false, 'http_errors' => false])
            ->connectTimeout(5)->timeout(12)->get('https://api.linkedin.com/v2/userinfo');
        $body = $reply->json();

        return $reply->status() === 200 && is_array($body)
            && (string) ($body['sub'] ?? '') === $ctx['external_account_id'];
    }
}
