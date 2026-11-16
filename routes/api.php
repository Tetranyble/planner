<?php

use App\Http\Controllers\Api\V1\AbortMediaPublicationPreparationController;
use App\Http\Controllers\Api\V1\AcceptOrganisationInvitationController;
use App\Http\Controllers\Api\V1\AcknowledgePrivacyRetentionReviewController;
use App\Http\Controllers\Api\V1\ActivateBrandApprovalPolicyController;
use App\Http\Controllers\Api\V1\AiCampaignProposalController;
use App\Http\Controllers\Api\V1\AiContentSuggestionController;
use App\Http\Controllers\Api\V1\ArmMediaPublicationRunController;
use App\Http\Controllers\Api\V1\ArmPublicationPlanController;
use App\Http\Controllers\Api\V1\ArmYouTubeTransferController;
use App\Http\Controllers\Api\V1\BrandAiUsageController;
use App\Http\Controllers\Api\V1\BrandApprovalPolicyController;
use App\Http\Controllers\Api\V1\BrandAudienceSegmentController;
use App\Http\Controllers\Api\V1\BrandClaimController;
use App\Http\Controllers\Api\V1\BrandClaimEvidenceController;
use App\Http\Controllers\Api\V1\BrandClaimPolicyController;
use App\Http\Controllers\Api\V1\BrandDirectoryController;
use App\Http\Controllers\Api\V1\BrandKnowledgeSourceController;
use App\Http\Controllers\Api\V1\BrandMembershipController;
use App\Http\Controllers\Api\V1\BrandOperationsDashboardController;
use App\Http\Controllers\Api\V1\BrandProfileController;
use App\Http\Controllers\Api\V1\BrandSocialAccountController;
use App\Http\Controllers\Api\V1\BrandStrategyController;
use App\Http\Controllers\Api\V1\BrandVoiceProfileController;
use App\Http\Controllers\Api\V1\BulkCancelPublicationIntentsController;
use App\Http\Controllers\Api\V1\BulkContentTargetsController;
use App\Http\Controllers\Api\V1\BulkPublicationPlanController;
use App\Http\Controllers\Api\V1\CancelMediaPublicationRunController;
use App\Http\Controllers\Api\V1\CancelPublicationIntentController;
use App\Http\Controllers\Api\V1\ChangeRecurringCampaignStateController;
use App\Http\Controllers\Api\V1\ConfirmGrantRevocationController;
use App\Http\Controllers\Api\V1\ConsentTikTokMediaRunController;
use App\Http\Controllers\Api\V1\ContentApprovalDecisionController;
use App\Http\Controllers\Api\V1\ContentApprovalRequestController;
use App\Http\Controllers\Api\V1\ContentItemController;
use App\Http\Controllers\Api\V1\ContentStudioController;
use App\Http\Controllers\Api\V1\ContentTargetController;
use App\Http\Controllers\Api\V1\ContentVersionController;
use App\Http\Controllers\Api\V1\CreateBrandController;
use App\Http\Controllers\Api\V1\CreateDraftFromCampaignProposalController;
use App\Http\Controllers\Api\V1\CreateOrganisationController;
use App\Http\Controllers\Api\V1\DecideGrantReconciliationController;
use App\Http\Controllers\Api\V1\DecidePublicationReconciliationController;
use App\Http\Controllers\Api\V1\GrantReconciliationController;
use App\Http\Controllers\Api\V1\GrantRevocationController;
use App\Http\Controllers\Api\V1\HoldRecurringCampaignOccurrenceController;
use App\Http\Controllers\Api\V1\InspectAiSuggestionClaimsController;
use App\Http\Controllers\Api\V1\InspectHeldPublicationPlansController;
use App\Http\Controllers\Api\V1\ListMetaDeletionReviewsController;
use App\Http\Controllers\Api\V1\ListPrivacyRetentionReviewsController;
use App\Http\Controllers\Api\V1\ListRecurringCampaignOccurrencesController;
use App\Http\Controllers\Api\V1\MediaAssetController;
use App\Http\Controllers\Api\V1\MediaPublicationPreparationController;
use App\Http\Controllers\Api\V1\MediaPublicationReleaseController;
use App\Http\Controllers\Api\V1\MediaPublicationRunController;
use App\Http\Controllers\Api\V1\OrganisationDirectoryController;
use App\Http\Controllers\Api\V1\OrganisationInvitationController;
use App\Http\Controllers\Api\V1\OrganisationMembershipController;
use App\Http\Controllers\Api\V1\PlannerAgentActionController;
use App\Http\Controllers\Api\V1\PlannerAgentWorkOrdersController;
use App\Http\Controllers\Api\V1\PlannerIntegrationCalendarController;
use App\Http\Controllers\Api\V1\PlannerIntegrationOperationsController;
use App\Http\Controllers\Api\V1\PlannerOperationalSafetyController;
use App\Http\Controllers\Api\V1\PlannerUtcpManualController;
use App\Http\Controllers\Api\V1\PreviewPublicationDeliveryController;
use App\Http\Controllers\Api\V1\PreviewRecurringCampaignController;
use App\Http\Controllers\Api\V1\PromoteAiContentSuggestionController;
use App\Http\Controllers\Api\V1\ProviderMetricSyncController;
use App\Http\Controllers\Api\V1\ProviderMetricSyncPolicyController;
use App\Http\Controllers\Api\V1\ProviderPerformanceDashboardController;
use App\Http\Controllers\Api\V1\PublicationCalendarController;
use App\Http\Controllers\Api\V1\PublicationIntentController;
use App\Http\Controllers\Api\V1\PublicationOperationsController;
use App\Http\Controllers\Api\V1\PublicationReadinessController;
use App\Http\Controllers\Api\V1\PublicationReconciliationController;
use App\Http\Controllers\Api\V1\QueryTikTokCreatorOptionsController;
use App\Http\Controllers\Api\V1\RecurringCampaignController;
use App\Http\Controllers\Api\V1\ReportAutomationController;
use App\Http\Controllers\Api\V1\ReschedulePublicationIntentController;
use App\Http\Controllers\Api\V1\ResolveMetaDeletionTargetController;
use App\Http\Controllers\Api\V1\ReviewAiCampaignProposalController;
use App\Http\Controllers\Api\V1\ReviewBrandClaimController;
use App\Http\Controllers\Api\V1\ReviewBrandClaimEvidenceController;
use App\Http\Controllers\Api\V1\ReviewBrandKnowledgeSourceController;
use App\Http\Controllers\Api\V1\RunReportAutomationController;
use App\Http\Controllers\Api\V1\SimulatePublicationDeliveryController;
use App\Http\Controllers\Api\V1\SocialMetricSnapshotController;
use App\Http\Controllers\Api\V1\StorageMediaCandidateController;
use App\Http\Controllers\Api\V1\SubmitBrandClaimForReviewController;
use App\Http\Controllers\Api\V1\UpdateReportAutomationStatusController;
use App\Http\Controllers\Api\V1\VerifySocialPublishingCapabilityController;
use App\Http\Controllers\Api\V1\WithdrawContentApprovalController;
use App\Http\Controllers\Api\V1\YouTubeTransferController;
use App\Http\Controllers\Api\V1\YouTubeTransferReleaseController;
use App\Http\Controllers\Callbacks\ReceiveMetaDataDeletionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::post('organisations', CreateOrganisationController::class)
        ->middleware(['verified', 'throttle:5,1'])
        ->name('api.v1.organisations.store');

    Route::get('organisations', OrganisationDirectoryController::class)
        ->name('api.v1.organisations.index');

    Route::post('organisations/{organisation:uuid}/brands', CreateBrandController::class)
        ->middleware(['verified', 'organisation.context', 'throttle:10,1'])
        ->middleware(['kinship.role:owner,manager', 'kinship.permission:brand.store'])
        ->name('api.v1.organisations.brands.store');

    Route::get('organisations/{organisation:uuid}/members', [OrganisationMembershipController::class, 'index'])
        ->middleware(['verified', 'organisation.context'])->middleware(['kinship.role:owner', 'kinship.permission:organisation_membership.index'])
        ->name('api.v1.organisations.members.index');
    Route::post('organisations/{organisation:uuid}/members/invitations', [OrganisationInvitationController::class, 'store'])
        ->middleware(['verified', 'organisation.context', 'throttle:10,1'])->middleware(['kinship.role:owner', 'kinship.permission:organisation_invitation.store'])
        ->name('api.v1.organisations.members.invite');
    Route::patch('organisations/{organisation:uuid}/members/{userId}/role', [OrganisationMembershipController::class, 'update'])
        ->middleware(['verified', 'organisation.context'])->whereNumber('userId')->middleware(['kinship.role:owner', 'kinship.permission:organisation_membership.update'])
        ->name('api.v1.organisations.members.change-role');
    Route::delete('organisations/{organisation:uuid}/members/{userId}', [OrganisationMembershipController::class, 'destroy'])
        ->middleware(['verified', 'organisation.context'])->whereNumber('userId')->middleware(['kinship.role:owner', 'kinship.permission:organisation_membership.delete'])
        ->name('api.v1.organisations.members.revoke');
    Route::delete('organisations/{organisation:uuid}/members/invitations/{uuid}', [OrganisationInvitationController::class, 'destroy'])
        ->middleware(['verified', 'organisation.context'])->whereUuid('uuid')->middleware(['kinship.role:owner', 'kinship.permission:organisation_invitation.delete'])
        ->name('api.v1.organisations.members.cancel-invitation');
    Route::post('invitations/accept', AcceptOrganisationInvitationController::class)
        ->middleware(['verified', 'throttle:10,1'])->name('api.v1.invitations.accept');

    Route::scopeBindings()->group(function (): void {
        Route::get('organisations/{organisation:uuid}/brands/{brand:uuid}/members', [BrandMembershipController::class, 'index'])
            ->middleware(['verified', 'organisation.context'])->middleware(['kinship.role:owner,manager', 'kinship.permission:brand_membership.index'])
            ->name('api.v1.organisations.brands.members.index');
        Route::post('organisations/{organisation:uuid}/brands/{brand:uuid}/members', [BrandMembershipController::class, 'store'])
            ->middleware(['verified', 'organisation.context', 'throttle:20,1'])->middleware(['kinship.role:owner,manager', 'kinship.permission:brand_membership.store'])
            ->name('api.v1.organisations.brands.members.add');
        Route::patch('organisations/{organisation:uuid}/brands/{brand:uuid}/members/{userId}/role', [BrandMembershipController::class, 'update'])
            ->middleware(['verified', 'organisation.context'])->whereNumber('userId')->middleware(['kinship.role:owner,manager', 'kinship.permission:brand_membership.update'])
            ->name('api.v1.organisations.brands.members.change-role');
        Route::delete('organisations/{organisation:uuid}/brands/{brand:uuid}/members/{userId}', [BrandMembershipController::class, 'destroy'])
            ->middleware(['verified', 'organisation.context'])->whereNumber('userId')->middleware(['kinship.role:owner,manager', 'kinship.permission:brand_membership.delete'])
            ->name('api.v1.organisations.brands.members.revoke');
    });

    Route::scopeBindings()->middleware('organisation.context')->prefix('organisations/{organisation:uuid}/brands/{brand:uuid}/strategy')
        ->name('api.v1.organisations.brands.strategy.')->group(function (): void {

            Route::get('/', [BrandStrategyController::class, 'show'])->middleware('kinship.permission:brand_profile.index')->name('show');
            Route::patch('/profile', [BrandProfileController::class, 'update'])->middleware(['verified', 'kinship.role:owner,manager', 'kinship.permission:brand_profile.update'])->name('profile');
            Route::post('/audiences', [BrandAudienceSegmentController::class, 'store'])->middleware(['verified', 'kinship.role:owner,manager,contributor', 'kinship.permission:brand_audience_segment.store'])->name('audience');
            Route::post('/voices', [BrandVoiceProfileController::class, 'store'])->middleware(['verified', 'kinship.role:owner,manager,contributor', 'kinship.permission:brand_voice_profile.store'])->name('voice');
            Route::patch('/claims/policy', [BrandClaimPolicyController::class, 'update'])->middleware(['verified', 'kinship.role:owner,manager', 'kinship.permission:brand_claim_policy.update'])->name('claim-policy');
            Route::post('/claims', [BrandClaimController::class, 'store'])->middleware(['verified', 'kinship.role:owner,manager,contributor', 'kinship.permission:brand_claim.store'])->name('claim');
            Route::post('/approval-policies', [BrandApprovalPolicyController::class, 'store'])->middleware(['verified', 'kinship.role:owner', 'kinship.permission:brand_approval_policy.store'])->name('approval-policy');
            Route::post('/knowledge-sources', [BrandKnowledgeSourceController::class, 'store'])->middleware(['verified', 'kinship.role:owner,manager,contributor', 'kinship.permission:brand_knowledge_source.store'])->name('sources.store');
            Route::post('/knowledge-sources/{sourceUuid}/review', ReviewBrandKnowledgeSourceController::class)->middleware(['verified', 'kinship.role:owner,manager', 'kinship.permission:knowledge-source.verify'])->name('sources.review');
            Route::post('/claims/{claimUuid}/evidence', [BrandClaimEvidenceController::class, 'store'])->middleware(['verified', 'kinship.role:owner,manager,contributor', 'kinship.permission:brand_claim_evidence.store'])->name('evidence.store');
            Route::post('/evidence/{evidenceUuid}/review', ReviewBrandClaimEvidenceController::class)->middleware(['verified', 'kinship.role:owner,manager', 'kinship.permission:claim-evidence.verify'])->name('evidence.review');
            Route::post('/claims/{claimUuid}/submit-review', SubmitBrandClaimForReviewController::class)->middleware(['verified', 'kinship.role:owner,manager,contributor', 'kinship.permission:claim.submit-review'])->name('claims.submit-review');
            Route::post('/claims/{claimUuid}/review', ReviewBrandClaimController::class)->middleware(['verified', 'kinship.role:owner,manager', 'kinship.permission:claim.review'])->name('claims.review');
            Route::post('/approval-policies/{policyUuid}/activate', ActivateBrandApprovalPolicyController::class)->middleware(['verified', 'kinship.role:owner', 'kinship.permission:approval-policy.activate'])->name('approval-policies.activate');
        });

    Route::scopeBindings()->middleware('organisation.context')
        ->get('organisations/{organisation:uuid}/brands/{brand:uuid}/social-accounts',
            [BrandSocialAccountController::class, 'index'])
        ->middleware('kinship.permission:social_account.index')
        ->name('api.v1.organisations.brands.social-accounts.index');

    Route::scopeBindings()->middleware(['verified', 'organisation.context'])
        ->post('organisations/{organisation:uuid}/brands/{brand:uuid}/social-accounts/{accountUuid}/verify-publishing',
            VerifySocialPublishingCapabilityController::class)
        ->whereUuid('accountUuid')->middleware(['kinship.role:owner,manager', 'kinship.permission:social-account.connect',
            'kinship.permission:approval_request.update', 'throttle:5,1'])
        ->name('api.v1.organisations.brands.social-accounts.verify-publishing');

    Route::get('organisations/{organisation:uuid}/brands', BrandDirectoryController::class)
        ->middleware('organisation.context')
        ->middleware(['kinship.permission:brand.index'])
        ->name('api.v1.organisations.brands.index');
});

// Meta-signed app callback: no user session, Kinship workspace or CSRF token.
Route::post('meta/data-deletion', ReceiveMetaDataDeletionController::class)
    ->middleware('throttle:30,1')->name('api.meta.data-deletion');

// Authorised privacy review endpoints, tenant-scoped and audited.
Route::middleware(['auth:sanctum', 'verified', 'organisation.context', 'kinship.role:owner', 'kinship.permission:privacy-deletion.review'])
    ->prefix('v1/organisations/{organisation:uuid}/privacy')->name('api.v1.organisations.privacy.')
    ->group(function (): void {
        Route::get('deletion-reviews', ListMetaDeletionReviewsController::class)->name('deletion-reviews.index');
        Route::get('retention-reviews', ListPrivacyRetentionReviewsController::class)->name('retention-reviews.index');
        Route::post('retention-reviews/{followupId}/acknowledge', AcknowledgePrivacyRetentionReviewController::class)
            ->whereNumber('followupId')->middleware('throttle:10,1')->name('retention-reviews.acknowledge');
        Route::post('deletion-reviews/{targetId}/resolve', ResolveMetaDeletionTargetController::class)
            ->whereNumber('targetId')->middleware('throttle:10,1')->name('deletion-reviews.resolve');
    });

// Grant revocation affects ALL linked accounts, not one brand. Owner-only, with explicit preview/confirm.
Route::middleware(['auth:sanctum', 'verified', 'organisation.context', 'kinship.role:owner', 'kinship.permission:social-grant.revoke'])
    ->scopeBindings()->prefix('v1/organisations/{organisation:uuid}')->name('api.v1.organisations.grants.')
    ->group(function (): void {
        Route::get('grant-revocations', [GrantRevocationController::class, 'index'])
            ->name('revocations.index');
        Route::post('brands/{brand:uuid}/social-accounts/{accountUuid}/grant-revocations',
            [GrantRevocationController::class, 'store'])
            ->whereUuid('accountUuid')->middleware('throttle:5,1')->name('revocations.store');
        Route::post('grant-revocations/{requestUuid}/confirm',
            ConfirmGrantRevocationController::class)
            ->whereUuid('requestUuid')->middleware('throttle:5,1')->name('revocations.confirm');
    });

// Independent two-owner reconciliation of uncertain/manual grant revocation outcomes.
Route::middleware(['auth:sanctum', 'verified', 'organisation.context', 'kinship.role:owner', 'kinship.permission:social-grant.reconcile'])
    ->prefix('v1/organisations/{organisation:uuid}')->name('api.v1.organisations.grants.')
    ->group(function (): void {
        Route::get('grant-reconciliations', [GrantReconciliationController::class, 'index'])
            ->name('reconciliations.index');
        Route::post('grant-revocations/{requestUuid}/reconciliation-evidence',
            [GrantReconciliationController::class, 'store'])
            ->whereUuid('requestUuid')->middleware('throttle:10,1')->name('reconciliations.store');
        Route::post('grant-revocations/{requestUuid}/reconcile',
            DecideGrantReconciliationController::class)
            ->whereUuid('requestUuid')->middleware('throttle:5,1')->name('reconciliations.decide');
    });

// Draft content studio. Platform targets are NOT publication jobs.
Route::middleware(['auth:sanctum', 'verified', 'organisation.context'])
    ->scopeBindings()->prefix('v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content')
    ->name('api.v1.content.')->group(function (): void {
        Route::get('/', [ContentStudioController::class, 'index'])
            ->middleware('kinship.permission:content_item.index')->name('index');
        Route::get('{itemUuid}', [ContentStudioController::class, 'show'])
            ->whereUuid('itemUuid')->middleware('kinship.permission:content_item.view')->name('show');
        Route::post('/', [ContentItemController::class, 'store'])
            ->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:content_item.store'])->name('store');
        Route::post('{itemUuid}/versions', [ContentVersionController::class, 'store'])
            ->whereUuid('itemUuid')->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:content_version.store'])->name('versions.store');
        Route::get('media-assets/storage-candidates', [StorageMediaCandidateController::class, 'index'])
            ->middleware('kinship.permission:media_asset.index')->name('media.storage-candidates');
        Route::post('media-assets', [MediaAssetController::class, 'store'])
            ->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:media_asset.store'])->name('media.store');

        // Read-only diagnostic. Never constitutes permission to schedule or publish.
        Route::get('{itemUuid}/targets/{targetUuid}/approvals/{requestId}/readiness',
            [PublicationReadinessController::class, 'show'])
            ->whereUuid('itemUuid')->whereUuid('targetUuid')->whereNumber('requestId')
            ->middleware('kinship.permission:approval_request.index')->name('readiness.show');

        // Operator-held publication intents. NO dispatch or scheduling side effects.
        Route::get('publication-intents', [PublicationIntentController::class, 'index'])
            ->middleware('kinship.permission:approval_request.index')->name('publication-intents.index');
        Route::post('{itemUuid}/targets/{targetUuid}/approvals/{requestId}/publication-intents',
            [PublicationIntentController::class, 'store'])
            ->whereUuid('itemUuid')->whereUuid('targetUuid')->whereNumber('requestId')
            ->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:approval_request.store', 'throttle:15,1'])
            ->name('publication-intents.store');
        Route::post('publication-intents/{jobId}/cancel', CancelPublicationIntentController::class)
            ->whereNumber('jobId')->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update'])
            ->name('publication-intents.cancel');

        Route::post('publication-intents/{jobId}/arm', ArmPublicationPlanController::class)
            ->whereNumber('jobId')->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:5,1'])->name('publication-intents.arm');
        Route::post('publication-intents/{jobId}/reschedule', ReschedulePublicationIntentController::class)
            ->whereNumber('jobId')->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:15,1'])
            ->name('publication-intents.reschedule');
        Route::post('publication-intents/bulk-cancel', BulkCancelPublicationIntentsController::class)
            ->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:5,1'])
            ->name('publication-intents.bulk-cancel');

        // Bounded, human-confirmed recurrence. Creating a template never arms or sends a post.
        Route::get('recurring-campaigns', [RecurringCampaignController::class, 'index'])
            ->middleware('kinship.permission:approval_request.index')->name('recurring-campaigns.index');
        Route::post('recurring-campaigns', [RecurringCampaignController::class, 'store'])
            ->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:approval_request.store', 'throttle:10,1'])
            ->name('recurring-campaigns.store');
        Route::get('recurring-campaigns/{scheduleId}/preview', PreviewRecurringCampaignController::class)
            ->whereNumber('scheduleId')->middleware('kinship.permission:approval_request.index')
            ->name('recurring-campaigns.preview');
        Route::get('recurring-campaigns/{scheduleId}/occurrences', ListRecurringCampaignOccurrencesController::class)
            ->whereNumber('scheduleId')->middleware('kinship.permission:approval_request.index')
            ->name('recurring-campaigns.occurrences');
        Route::post('recurring-campaigns/{scheduleId}/state', ChangeRecurringCampaignStateController::class)
            ->whereNumber('scheduleId')->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:10,1'])
            ->name('recurring-campaigns.state');
        Route::post('recurring-campaigns/{scheduleId}/hold', HoldRecurringCampaignOccurrenceController::class)
            ->whereNumber('scheduleId')->middleware(['kinship.role:owner,manager,contributor',
                'kinship.permission:approval_request.store', 'throttle:10,1'])
            ->name('recurring-campaigns.hold');

        // Stage-fenced media execution desk; no caller-supplied receipts or dispatch options.
        // YouTube resumable transfer desk. Session URLs, tokens and video bytes never enter JSON.
        Route::get('youtube-transfers/release', YouTubeTransferReleaseController::class)
            ->middleware('kinship.permission:approval_request.index')->name('youtube-transfers.release');
        Route::get('youtube-transfers', [YouTubeTransferController::class, 'index'])
            ->middleware('kinship.permission:approval_request.index')->name('youtube-transfers.index');
        Route::post('youtube-transfers', [YouTubeTransferController::class, 'store'])
            ->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:5,1'])
            ->name('youtube-transfers.store');
        Route::get('youtube-transfers/{transferId}', [YouTubeTransferController::class, 'show'])
            ->whereNumber('transferId')->middleware('kinship.permission:approval_request.index')
            ->name('youtube-transfers.show');
        Route::post('youtube-transfers/{transferId}/arm', ArmYouTubeTransferController::class)
            ->whereNumber('transferId')->middleware(['kinship.role:owner,manager',
                'kinship.permission:approval_request.update', 'throttle:5,1'])
            ->name('youtube-transfers.arm');
        Route::get('media-publication-runs', [MediaPublicationRunController::class, 'index'])
            ->middleware('kinship.permission:approval_request.index')->name('media-publication-runs.index');
        Route::post('media-publication-runs', [MediaPublicationRunController::class, 'store'])
            ->middleware(['verified', 'kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:10,1'])
            ->name('media-publication-runs.store');
        Route::get('media-publication-runs/{runId}', [MediaPublicationRunController::class, 'show'])
            ->whereNumber('runId')->middleware('kinship.permission:approval_request.index')->name('media-publication-runs.show');
        Route::post('media-publication-runs/{runId}/cancel', CancelMediaPublicationRunController::class)
            ->whereNumber('runId')->middleware(['verified', 'kinship.role:owner,manager',
                'kinship.permission:approval_request.update', 'throttle:5,1'])->name('media-publication-runs.cancel');
        Route::post('media-publication-runs/{runId}/arm', ArmMediaPublicationRunController::class)
            ->whereNumber('runId')->middleware(['verified', 'kinship.role:owner,manager',
                'kinship.permission:approval_request.update', 'throttle:5,1'])->name('media-publication-runs.arm');

        // TikTok creator data is fetched from the actual OAuth account; options are never trusted from the client.
        Route::get('media-publication-runs/{runId}/tiktok-creator-options', QueryTikTokCreatorOptionsController::class)
            ->whereNumber('runId')->middleware(['verified', 'kinship.role:owner,manager',
                'kinship.permission:approval_request.update', 'throttle:5,1'])->name('media-publication-runs.tiktok-options');
        Route::post('media-publication-runs/{runId}/tiktok-consent', ConsentTikTokMediaRunController::class)
            ->whereNumber('runId')->middleware(['verified', 'kinship.role:owner,manager',
                'kinship.permission:approval_request.update', 'throttle:5,1'])->name('media-publication-runs.tiktok-consent');
        Route::get('media-publication-release', MediaPublicationReleaseController::class)
            ->middleware('kinship.permission:approval_request.index')->name('media-publications.release');

        // Three-provider media preparation desk. Never creates remote HTTP sends or publication attempts.
        Route::get('media-publication-preparations', [MediaPublicationPreparationController::class, 'index'])
            ->middleware('kinship.permission:approval_request.index')->name('media-publications.index');
        Route::post('media-publication-preparations', [MediaPublicationPreparationController::class, 'store'])
            ->middleware(['verified', 'kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:10,1'])
            ->name('media-publications.store');
        Route::get('media-publication-preparations/{preparationId}', [MediaPublicationPreparationController::class, 'show'])
            ->whereNumber('preparationId')->middleware('kinship.permission:approval_request.index')->name('media-publications.show');
        Route::post('media-publication-preparations/{preparationId}/abort', AbortMediaPublicationPreparationController::class)
            ->whereNumber('preparationId')->middleware(['verified', 'kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:10,1'])
            ->name('media-publications.abort');

        // Bounded, read-only approval/media preflight for existing held plans.
        Route::post('publication-intents/inspect', InspectHeldPublicationPlansController::class)
            ->middleware(['kinship.permission:approval_request.index', 'throttle:10,1'])
            ->name('publication-intents.inspect');

        // Atomic multi-target operator holds; these never enter the dispatch queue.
        Route::post('publication-intents/bulk', BulkPublicationPlanController::class)
            ->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:approval_request.store', 'throttle:5,1'])
            ->name('publication-intents.bulk');

        // Campaign strategies are AI-assisted proposals only, never executable plans.
        Route::get('ai-campaign-proposals', [AiCampaignProposalController::class, 'index'])
            ->middleware('kinship.permission:content_item.index')->name('ai-campaign-proposals.index');
        Route::post('ai-campaign-proposals', [AiCampaignProposalController::class, 'store'])
            ->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:content_item.store', 'throttle:3,1'])
            ->name('ai-campaign-proposals.store');
        Route::post('ai-campaign-proposals/{proposalUuid}/review', ReviewAiCampaignProposalController::class)
            ->whereUuid('proposalUuid')->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:10,1'])
            ->name('ai-campaign-proposals.review');

        // Explicit editorial conversion. AI campaign approval never authorizes publishing.
        Route::post('ai-campaign-proposals/{proposalUuid}/draft', CreateDraftFromCampaignProposalController::class)
            ->whereUuid('proposalUuid')->middleware(['kinship.role:owner,manager,contributor',
                'kinship.permission:content_item.store', 'throttle:10,1'])
            ->name('ai-campaign-proposals.draft');

        // Separate read permission and service authorization; excludes other brands' usage.
        Route::get('ai-usage', BrandAiUsageController::class)
            ->middleware('kinship.permission:content_item.index')->name('ai-usage.index');

        // AI copy is opt-in and never auto-schedules or auto-publishes.
        Route::get('ai-suggestions', [AiContentSuggestionController::class, 'index'])
            ->middleware('kinship.permission:content_item.index')->name('ai-suggestions.index');
        Route::post('ai-suggestions', [AiContentSuggestionController::class, 'store'])
            ->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:content_item.store', 'throttle:5,1'])
            ->name('ai-suggestions.store');
        Route::get('ai-suggestions/{suggestionUuid}/claim-review', InspectAiSuggestionClaimsController::class)
            ->whereUuid('suggestionUuid')->middleware('kinship.permission:content_item.index')
            ->name('ai-suggestions.claim-review');
        Route::post('ai-suggestions/{suggestionUuid}/promote', PromoteAiContentSuggestionController::class)
            ->whereUuid('suggestionUuid')->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:content_item.store', 'throttle:10,1'])
            ->name('ai-suggestions.promote');

        Route::get('social-metrics', [SocialMetricSnapshotController::class, 'index'])
            ->middleware('kinship.permission:approval_request.index')->name('social-metrics.index');
        Route::post('social-metrics', [SocialMetricSnapshotController::class, 'store'])
            ->middleware(['verified', 'kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:10,1'])
            ->name('social-metrics.store');

        // Provider-read observations are separate from operator-submitted evidence.
        Route::get('social-metrics/provider-syncs', [ProviderMetricSyncController::class, 'index'])
            ->middleware('kinship.permission:approval_request.index')->name('social-metrics.provider-syncs.index');
        Route::post('social-metrics/provider-syncs', [ProviderMetricSyncController::class, 'store'])
            ->middleware(['verified', 'kinship.role:owner,manager',
                'kinship.permission:approval_request.update', 'throttle:5,1'])
            ->name('social-metrics.provider-syncs.store');

        Route::get('social-metrics/provider-sync-schedule', [ProviderMetricSyncPolicyController::class, 'index'])
            ->middleware('kinship.permission:approval_request.index')->name('social-metrics.provider-sync-schedule.index');
        Route::put('social-metrics/provider-sync-schedule', [ProviderMetricSyncPolicyController::class, 'update'])
            ->middleware(['verified', 'kinship.role:owner,manager',
                'kinship.permission:approval_request.update', 'throttle:5,1'])
            ->name('social-metrics.provider-sync-schedule.update');

        Route::get('social-metrics/performance-insights',
            ProviderPerformanceDashboardController::class)
            ->middleware('kinship.permission:approval_request.index')->name('social-metrics.performance-insights');

        Route::get('operations-dashboard', BrandOperationsDashboardController::class)
            ->middleware('kinship.permission:approval_request.index')->name('operations-dashboard.index');

        Route::get('publication-calendar', PublicationCalendarController::class)
            ->middleware('kinship.permission:approval_request.index')
            ->name('publication-calendar.index');

        // Tenant-safe publication operations: status/history only; no state mutation.
        Route::get('publication-operations', [PublicationOperationsController::class, 'index'])
            ->middleware('kinship.permission:approval_request.index')->name('publication-operations.index');
        Route::get('publication-operations/{jobId}', [PublicationOperationsController::class, 'show'])
            ->whereNumber('jobId')->middleware('kinship.permission:approval_request.index')->name('publication-operations.show');

        Route::post('publication-delivery/preview',
            PreviewPublicationDeliveryController::class)
            ->middleware(['kinship.permission:approval_request.index', 'throttle:30,1'])->name('publication-delivery.preview');

        // Hermetic diagnostics; never modifies jobs or contacts a platform.
        Route::post('publication-delivery/simulate',
            SimulatePublicationDeliveryController::class)
            ->middleware(['kinship.permission:approval_request.index', 'throttle:15,1'])->name('publication-delivery.simulate');

        // Two-person reconciliation: attestations do not authorize replays or alter provider status.
        Route::get('publication-operations/{jobId}/reconciliation',
            [PublicationReconciliationController::class, 'show'])
            ->whereNumber('jobId')->middleware('kinship.permission:approval_request.index')->name('publication-reconciliation.show');
        Route::post('publication-operations/{jobId}/reconciliation/evidence',
            [PublicationReconciliationController::class, 'store'])
            ->whereNumber('jobId')->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:10,1'])->name('publication-reconciliation.store');
        Route::post('publication-operations/{jobId}/reconciliation/decision',
            DecidePublicationReconciliationController::class)
            ->whereNumber('jobId')->middleware(['kinship.role:owner,manager', 'kinship.permission:content.approve', 'throttle:5,1'])->name('publication-reconciliation.decide');

        // Review requests pin immutable target/version/media/policy snapshots. NEVER publish here.
        Route::get('{itemUuid}/approvals', [ContentApprovalRequestController::class, 'index'])
            ->whereUuid('itemUuid')->middleware('kinship.permission:approval_request.index')->name('approvals.index');
        Route::post('{itemUuid}/targets/{targetUuid}/approvals', [ContentApprovalRequestController::class, 'store'])
            ->whereUuid('itemUuid')->whereUuid('targetUuid')->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:approval_request.store'])->name('approvals.store');
        Route::post('{itemUuid}/approvals/{requestId}/decisions', [ContentApprovalDecisionController::class, 'store'])
            ->whereUuid('itemUuid')->whereNumber('requestId')->middleware(['kinship.role:owner,manager', 'kinship.permission:content.approve'])->name('approvals.decide');
        Route::post('{itemUuid}/approvals/{requestId}/withdraw', WithdrawContentApprovalController::class)
            ->whereUuid('itemUuid')->whereNumber('requestId')->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:approval_request.update'])->name('approvals.withdraw');
        Route::post('{itemUuid}/targets/bulk', BulkContentTargetsController::class)
            ->whereUuid('itemUuid')->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:content_target.store', 'throttle:10,1'])->name('targets.bulk');
        Route::post('{itemUuid}/targets', [ContentTargetController::class, 'store'])
            ->whereUuid('itemUuid')->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:content_target.store'])->name('targets.store');
    });

// Opt-in internal report-only automation: manual and daily UTC. No provider IO or publication actions.
Route::middleware(['auth:sanctum', 'verified', 'organisation.context'])
    ->scopeBindings()->prefix('v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content/report-automations')
    ->name('api.v1.content.report-automations.')->group(function (): void {
        Route::get('/', [ReportAutomationController::class, 'index'])
            ->middleware('kinship.permission:approval_request.index')->name('index');
        Route::post('/', [ReportAutomationController::class, 'store'])
            ->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:10,1'])->name('store');
        Route::post('{ruleId}/status', UpdateReportAutomationStatusController::class)
            ->whereNumber('ruleId')->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:15,1'])->name('status');
        Route::post('{ruleId}/run', RunReportAutomationController::class)
            ->whereNumber('ruleId')->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:15,1'])->name('run');
    });

// Increment 60: dedicated, scoped developer read access. All publish/mutation verbs excluded.
// Tokens bind one user and one brand; sessions cannot access external read endpoints.
Route::prefix('v1/integrations/organisations/{organisation:uuid}/brands/{brand:uuid}')
    ->middleware(['auth:sanctum', 'verified', 'organisation.context'])->scopeBindings()
    ->name('api.v1.integrations.')->group(function (): void {
        Route::middleware(['planner.integration', 'kinship.permission:approval_request.index', 'throttle:30,1'])
            ->group(function (): void {
                Route::get('safety', PlannerOperationalSafetyController::class)
                    ->name('safety');
                Route::get('utcp', PlannerUtcpManualController::class)
                    ->name('utcp');
                Route::get('operations', [PlannerIntegrationOperationsController::class, 'index'])
                    ->name('operations');
                Route::get('calendar', PlannerIntegrationCalendarController::class)
                    ->name('calendar');
                Route::get('{resource}', [PlannerIntegrationOperationsController::class, 'show'])
                    ->whereIn('resource', ['provider-performance', 'publication-operations'])
                    ->name('resource');
            });
    });

// Guarded agent action boundary: bearer scope comes from token, NEVER supplied by the agent.
Route::prefix('v1/agent')->middleware(['auth:sanctum', 'verified', 'planner.agent', 'throttle:20,1'])
    ->name('api.v1.agent.')->group(function (): void {
        Route::get('work-orders', PlannerAgentWorkOrdersController::class)
            ->name('work-orders');
        Route::post('action', PlannerAgentActionController::class)
            ->name('action');
    });
