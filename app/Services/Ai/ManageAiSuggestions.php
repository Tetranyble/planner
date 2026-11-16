<?php

namespace App\Services\Ai;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\BrandClaim;
use App\Models\BrandProfile;
use App\Models\BrandVoiceProfile;
use App\Models\ContentItem;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AiSuggestionRepositoryContract;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Services\Content\ManageContentStudio;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ManageAiSuggestions
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly AiSuggestionRepositoryContract $repo,
        private readonly AuditEventRepositoryContract $audit,
        private readonly GenerateBrandCopy $generator,
        private readonly ManageContentStudio $studio,
        private readonly BrandAiUsageBudget $budget) {}

    private function check(User $user, Organisation $org, Brand $brand, string $permission): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id || ! $this->guard->allows($user, $brand, $permission)) {
            throw new AuthorizationException('Brand AI permission denied.');
        }
    }

    public function listing(User $user, Organisation $org, Brand $brand): array
    {
        $this->check($user, $org, $brand, 'content_item.index');

        return $this->repo->list($org, $brand)->through(fn ($s) => [
            'uuid' => $s->uuid, 'topic' => $s->topic, 'platform' => $s->platform,
            'campaign_name' => $s->campaign_name, 'text' => $s->suggestion_text,
            'status' => $s->status, 'created_at' => $s->created_at?->toIso8601String(),
        ])->toArray();
    }

    public function generate(User $user, Organisation $org, Brand $brand, array $input): array
    {
        $this->check($user, $org, $brand, 'content_item.store');
        $profile = BrandProfile::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)->first();
        $voice = BrandVoiceProfile::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('active', true)->orderByDesc('is_default')->first();
        // Approved claims only. Other evidence and draft claims never enter the prompt.
        $claims = BrandClaim::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('status', 'APPROVED')->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->limit(8)->pluck('claim_text')->all();
        $context = ['brand' => $brand->name, 'positioning' => $profile?->positioning,
            'voice' => $voice?->tone_description, 'approved_claims' => $claims,
            'platform' => $input['platform'], 'topic' => $input['topic'],
            'campaign' => $input['campaign_name'] ?? null, 'audience' => $input['audience'] ?? null,
            'goal' => $input['goal'] ?? null];
        // Network call intentionally occurs BEFORE the database write transaction.
        $reservation = $this->budget->reserve($org, $brand, $user, 'COPY');
        try {
            $generated = $this->generator->generate($context);
            $saved = DB::transaction(function () use ($user, $org, $brand, $input, $generated) {
                $this->check($user, $org, $brand, 'content_item.store');
                $suggestion = $this->repo->create($org, $brand, $user, [
                    'topic' => $input['topic'], 'platform' => $input['platform'],
                    'campaign_name' => $input['campaign_name'] ?? null,
                    'suggestion_text' => $generated['text'], 'model' => $generated['model'],
                    'prompt_hash' => $generated['prompt_hash'], 'result_hash' => $generated['result_hash'],
                ]);
                $this->audit->record($org, $user, 'content.ai_suggestion.created', 'ai_content_suggestion', $suggestion->uuid, $brand, [
                    'model' => $suggestion->model, 'result_hash' => $suggestion->result_hash]);

                return ['uuid' => $suggestion->uuid, 'text' => $suggestion->suggestion_text, 'status' => $suggestion->status];
            }, 3);
            $this->budget->settle($reservation, true, $generated['model'], $generated['result_hash'], $generated['provider_usage'] ?? null);

            return $saved;
        } catch (\Throwable $error) {
            $this->budget->settle($reservation, false);
            throw $error;
        }
    }

    public function promote(User $user, Organisation $org, Brand $brand, string $uuid, array $input): array
    {
        $this->check($user, $org, $brand, 'content_item.store');

        return DB::transaction(function () use ($user, $org, $brand, $uuid, $input) {
            $s = $this->repo->find($org, $brand, $uuid, true);
            if ($s->status !== 'SUGGESTED') {
                throw new ConflictHttpException('This suggestion was already promoted.');
            }
            if (! hash_equals($s->result_hash, hash('sha256', $s->suggestion_text))) {
                throw new ConflictHttpException('AI suggestion changed unexpectedly.');
            }
            // Human explicitly chooses to promote an editable draft; normal approval still required.
            $result = $this->studio->create($user, $org, $brand, [
                'title' => $input['title'], 'content_kind' => 'TEXT', 'copy_text' => $s->suggestion_text,
                'media_uuids' => [], 'ai_generated' => true, 'includes_sensitive_claim' => null,
            ]);
            $item = ContentItem::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
                ->where('uuid', $result['uuid'])->firstOrFail();
            $this->repo->promote($s,$user,(int) $item->id);
            $this->audit->record($org,$user,'content.ai_suggestion.promoted','ai_content_suggestion',$s->uuid,$brand,
                ['content_item_uuid' => $item->uuid]);

            return ['content_item_uuid' => $item->uuid, 'version_uuid' => $result['version_uuid'], 'state' => 'DRAFT'];
        }, 3);
    }
}
