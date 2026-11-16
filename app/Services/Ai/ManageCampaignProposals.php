<?php

namespace App\Services\Ai;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Ai\ProviderEvidenceContext;
use App\Models\Brand;
use App\Models\BrandClaim;
use App\Models\BrandProfile;
use App\Models\BrandVoiceProfile;
use App\Models\ContentItem;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AiCampaignProposalRepositoryContract;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\ContentStudioRepositoryContract;
use App\Repository\Contracts\ProviderMetricSyncRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Proposal-only campaign strategy. Approval is an editorial decision, never publication authorization. */
final class ManageCampaignProposals
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly AiCampaignProposalRepositoryContract $repo,
        private readonly AuditEventRepositoryContract $audit,
        private readonly GenerateBrandCopy $generator,
        private readonly BrandAiUsageBudget $budget,
        private readonly ContentStudioRepositoryContract $content,
        private readonly ProviderMetricSyncRepositoryContract $providerMetrics) {}

    private function authorize(User $actor, Organisation $org, Brand $brand, string $permission): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id || ! $this->guard->allows($actor, $brand, $permission)) {
            throw new AuthorizationException('Campaign permission denied.');
        }
    }

    public function listing(User $actor, Organisation $org, Brand $brand): array
    {
        $this->authorize($actor, $org, $brand, 'content_item.index');

        return $this->repo->list($org, $brand)->through(fn ($p) => [
            'uuid' => $p->uuid, 'title' => $p->title, 'objective' => $p->objective,
            'audience' => $p->audience, 'platforms' => $p->platforms,
            'duration_days' => $p->duration_days, 'proposal_text' => $p->proposal_text,
            'status' => $p->status, 'provider_evidence_hash' => $p->provider_evidence_hash,
            'provider_evidence_snapshot' => $p->provider_evidence_snapshot,
            'created_at' => $p->created_at?->toIso8601String(),
            'reviewed_at' => $p->reviewed_at?->toIso8601String(), 'review_note' => $p->review_note,
        ])->toArray();
    }

    public function propose(User $actor, Organisation $org, Brand $brand, array $input): array
    {
        $this->authorize($actor, $org, $brand, 'content_item.store');
        $profile = BrandProfile::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)->first();
        $voice = BrandVoiceProfile::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('active', true)->orderByDesc('is_default')->first();
        $claims = BrandClaim::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('status', 'APPROVED')->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->limit(8)->pluck('claim_text')->all();
        $evidence = null;
        if ($input['use_provider_evidence'] ?? false) {
            // Read-only, brand-scoped snapshots; never import manual/user-reported data as verified.
            $signals = $this->providerMetrics->matchedTrendsByProvider($org, $brand);
            $evidence = ProviderEvidenceContext::fromSignals($signals, $input['platforms']);
            if (! $evidence['eligible']) {
                throw ValidationException::withMessages([
                    'use_provider_evidence' => 'At least three matched verified provider observations are required for a selected platform.']);
            }
        }
        $reservation = $this->budget->reserve($org, $brand, $actor, 'CAMPAIGN');
        try {
            $result = $this->generator->generate([
                'task' => 'DRAFT CAMPAIGN PROPOSAL ONLY. Produce campaign objectives, audience segments, '
                    .'a day-by-day editorial outline with platform-specific ideas, KPIs, risks, '
                    .'and explicit factual verification tasks. Do not fabricate evidence, prices, or ROI. '
                    .'No automatic publication or campaign execution.',
                'brand' => $brand->name, 'positioning' => $profile?->positioning, 'voice' => $voice?->tone_description,
                'approved_claims' => $claims, 'campaign_title' => $input['title'],
                'objective' => $input['objective'], 'audience' => $input['audience'],
                'platforms' => $input['platforms'], 'duration_days' => $input['duration_days'],
                'performance_evidence' => $evidence['snapshot'] ?? null,
                'evidence_rules' => 'Evidence is observational and not causal. Never claim attribution, ROI, endorsements or performance guarantees.',
            ]);
            // Network I/O already completed outside the transaction. Reauthorize before persistence.
            $saved = DB::transaction(function () use ($actor, $org, $brand, $input, $result, $evidence) {
                $this->authorize($actor, $org, $brand, 'content_item.store');
                $proposal = $this->repo->create($org, $brand, $actor, [
                    ...array_diff_key($input, ['use_provider_evidence' => true]),
                    'provider_evidence_hash' => $evidence['sha256'] ?? null,
                    'provider_evidence_snapshot' => $evidence['snapshot'] ?? null,
                    'proposal_text' => $result['text'], 'model' => $result['model'],
                    'prompt_hash' => $result['prompt_hash'], 'result_hash' => $result['result_hash'],
                ]);
                $this->audit->record($org, $actor, 'campaign.ai_proposal.created', 'ai_campaign_proposal', $proposal->uuid, $brand,
                    ['result_hash' => $proposal->result_hash, 'model' => $proposal->model,
                        'provider_evidence_hash' => $proposal->provider_evidence_hash]);

                return ['uuid' => $proposal->uuid, 'status' => 'PROPOSED'];
            }, 3);
            $this->budget->settle($reservation, true, $result['model'], $result['result_hash'], $result['provider_usage'] ?? null);

            return $saved;
        } catch (\Throwable $error) {
            $this->budget->settle($reservation, false);
            throw $error;
        }
    }

    /** An approved AI campaign is a strategy, not a publish approval. An editor explicitly
     * authors a new immutable content draft and records the approved proposal provenance. */
    public function createEditorialDraft(User $actor, Organisation $org, Brand $brand, string $uuid, array $input): array
    {
        $this->authorize($actor, $org, $brand, 'content_item.store');

        return DB::transaction(function () use ($actor, $org, $brand, $uuid, $input): array {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $lockedBrand = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)
                ->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $lockedBrand, 'content_item.store');
            $proposal = $this->repo->find($org, $lockedBrand, $uuid, true);
            if ($proposal->status !== 'APPROVED' || ! $proposal->reviewed_by_user_id || ! $proposal->reviewed_at) {
                throw new ConflictHttpException('Only independently approved campaign proposals can seed a draft.');
            }
            if ((int) $proposal->reviewed_by_user_id === (int) $proposal->requested_by_user_id) {
                throw new ConflictHttpException('Campaign approval lacks independent review.');
            }
            if ($proposal->provider_evidence_hash && ! hash_equals((string) $proposal->provider_evidence_hash,
                ProviderEvidenceContext::digest($proposal->provider_evidence_snapshot ?? []))) {
                throw new ConflictHttpException('Campaign provider evidence integrity mismatch.');
            }
            if (! hash_equals((string) $proposal->result_hash, hash('sha256', (string) $proposal->proposal_text))) {
                throw new ConflictHttpException('Approved campaign proposal integrity mismatch.');
            }
            $existing = $this->repo->existingDraft($org, $lockedBrand, $proposal);
            if ($existing) {
                $item = $this->content->findItem($org, $lockedBrand, (string) ContentItem::query()
                    ->where('organisation_id', $org->id)->where('brand_id', $brand->id)
                    ->whereKey($existing->content_item_id)->firstOrFail()->uuid);

                return ['content_item_uuid' => $item->uuid, 'already_created' => true, 'state' => 'DRAFT'];
            }
            $item = $this->content->createItem($org, $lockedBrand, $actor, [
                'title' => $input['title'], 'content_kind' => 'TEXT',
            ]);
            $version = $this->content->appendVersion($org, $lockedBrand, $item, $actor, 1,
                $input['copy_text'], [], true, null);
            $this->repo->linkDraft($org, $lockedBrand, $proposal, $item, $actor);
            $this->audit->record($org, $actor, 'campaign.ai_proposal.draft_created', 'content_item', $item->uuid, $lockedBrand,
                ['proposal_uuid' => $proposal->uuid, 'approved_result_hash' => $proposal->result_hash,
                    'content_version_hash' => $version->content_hash, 'publication_authorized' => false]);

            return ['content_item_uuid' => $item->uuid, 'content_version_uuid' => $version->uuid,
                'already_created' => false, 'state' => 'DRAFT', 'publishing_enabled' => false];
        }, 3);
    }

    public function review(User $reviewer, Organisation $org, Brand $brand, string $uuid, array $input): array
    {
        $this->authorize($reviewer, $org, $brand, 'approval_request.update');

        return DB::transaction(function () use ($reviewer, $org, $brand, $uuid, $input) {
            $this->authorize($reviewer, $org, $brand, 'approval_request.update');
            $proposal = $this->repo->find($org, $brand, $uuid, true);
            if ($proposal->status !== 'PROPOSED') {
                throw new ConflictHttpException('Campaign proposal is already reviewed.');
            }
            if ((int) $proposal->requested_by_user_id === (int) $reviewer->id) {
                throw new AuthorizationException('Campaign author cannot independently review their own proposal.');
            }
            if ($proposal->provider_evidence_hash && ! hash_equals((string) $proposal->provider_evidence_hash,
                ProviderEvidenceContext::digest($proposal->provider_evidence_snapshot ?? []))) {
                throw new ConflictHttpException('Campaign provider evidence integrity mismatch.');
            }
            if (! hash_equals($proposal->result_hash,hash('sha256',$proposal->proposal_text))) {
                throw new ConflictHttpException('Campaign proposal integrity mismatch.');
            }
            $this->repo->review($proposal,$reviewer,$input['decision'],$input['note']);
            $this->audit->record($org,$reviewer,'campaign.ai_proposal.reviewed','ai_campaign_proposal',$proposal->uuid,$brand,
                ['decision' => $input['decision'], 'result_hash' => $proposal->result_hash]);

            return ['uuid' => $proposal->uuid, 'status' => $proposal->status];
        }, 3);
    }
}
