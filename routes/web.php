<?php

use App\Http\Controllers\Api\V1\CancelPlannerAutonomousResearchController;
use App\Http\Controllers\Api\V1\DownloadPrivateCreativePreviewController;
use App\Http\Controllers\Api\V1\FinalizeCreativeImageHandoffController;
use App\Http\Controllers\Api\V1\PlannerAgentDelegationController;
use App\Http\Controllers\Api\V1\PlannerAgentWorkOrderReviewController;
use App\Http\Controllers\Api\V1\PlannerIntegrationTokenController;
use App\Http\Controllers\Api\V1\PlannerOperationalSafetyController;
use App\Http\Controllers\Api\V1\PromotePlannerAutonomousResourceController;
use App\Http\Controllers\Api\V1\RevisePlannerAutonomousResourceController;
use App\Http\Controllers\Api\V1\StartPlannerAutonomousResearchController;
use App\Http\Controllers\Callbacks\ShowMetaDataDeletionStatusController;
use App\Http\Controllers\Callbacks\ShowPrivacyDeletionInstructionsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Workspace\AcceptOrganisationInvitationController;
use App\Http\Controllers\Workspace\AcknowledgePrivacyRetentionReviewController;
use App\Http\Controllers\Workspace\ActivateBrandApprovalPolicyController;
use App\Http\Controllers\Workspace\BrandApprovalPolicyController;
use App\Http\Controllers\Workspace\BrandAudienceSegmentController;
use App\Http\Controllers\Workspace\BrandClaimController;
use App\Http\Controllers\Workspace\BrandClaimEvidenceController;
use App\Http\Controllers\Workspace\BrandClaimPolicyController;
use App\Http\Controllers\Workspace\BrandDirectoryController;
use App\Http\Controllers\Workspace\BrandKnowledgeSourceController;
use App\Http\Controllers\Workspace\BrandMembersController;
use App\Http\Controllers\Workspace\BrandOnboardingController;
use App\Http\Controllers\Workspace\BrandProfileController;
use App\Http\Controllers\Workspace\BrandSocialAccountController;
use App\Http\Controllers\Workspace\BrandStrategyController;
use App\Http\Controllers\Workspace\BrandVoiceProfileController;
use App\Http\Controllers\Workspace\BulkContentTargetsController;
use App\Http\Controllers\Workspace\CompleteSocialOAuthController;
use App\Http\Controllers\Workspace\ConfirmGrantRevocationController;
use App\Http\Controllers\Workspace\ConfirmSocialOAuthSelectionController;
use App\Http\Controllers\Workspace\ContentApprovalDecisionController;
use App\Http\Controllers\Workspace\ContentApprovalRequestController;
use App\Http\Controllers\Workspace\ContentItemController;
use App\Http\Controllers\Workspace\ContentStudioController;
use App\Http\Controllers\Workspace\ContentTargetController;
use App\Http\Controllers\Workspace\ContentVersionController;
use App\Http\Controllers\Workspace\DecideGrantReconciliationController;
use App\Http\Controllers\Workspace\GrantReconciliationController;
use App\Http\Controllers\Workspace\GrantRevocationController;
use App\Http\Controllers\Workspace\MediaAssetController;
use App\Http\Controllers\Workspace\MetaDeletionReviewController;
use App\Http\Controllers\Workspace\OrganisationDirectoryController;
use App\Http\Controllers\Workspace\OrganisationInvitationController;
use App\Http\Controllers\Workspace\OrganisationInvitationManagementController;
use App\Http\Controllers\Workspace\OrganisationMembersController;
use App\Http\Controllers\Workspace\OrganisationOnboardingController;
use App\Http\Controllers\Workspace\ResolveMetaDeletionTargetController;
use App\Http\Controllers\Workspace\ReviewBrandClaimController;
use App\Http\Controllers\Workspace\ReviewBrandClaimEvidenceController;
use App\Http\Controllers\Workspace\ReviewBrandKnowledgeSourceController;
use App\Http\Controllers\Workspace\ShowBrandCreationFormController;
use App\Http\Controllers\Workspace\ShowOrganisationCreationFormController;
use App\Http\Controllers\Workspace\SocialOAuthSelectionController;
use App\Http\Controllers\Workspace\StartSocialOAuthController;
use App\Http\Controllers\Workspace\SubmitBrandClaimForReviewController;
use App\Http\Controllers\Workspace\VerifySocialPublishingCapabilityController;
use App\Http\Controllers\Workspace\WithdrawContentApprovalController;
use App\Social\SocialProviderRegistry;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/organisations/create', ShowOrganisationCreationFormController::class)
        ->name('organisations.create');
    Route::post('/organisations', [OrganisationOnboardingController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('organisations.store');

    Route::get('/organisations', OrganisationDirectoryController::class)
        ->name('organisations.index');
    Route::get('/organisations/{organisation:uuid}/brands/create', ShowBrandCreationFormController::class)
        ->middleware('organisation.context')
        ->middleware(['kinship.role:owner,manager', 'kinship.permission:brand.store'])
        ->name('organisations.brands.create');
    Route::post('/organisations/{organisation:uuid}/brands', [BrandOnboardingController::class, 'store'])
        ->middleware(['organisation.context', 'throttle:10,1'])
        ->middleware(['kinship.role:owner,manager', 'kinship.permission:brand.store'])
        ->name('organisations.brands.store');

    Route::get('/organisations/{organisation:uuid}/members', [OrganisationMembersController::class, 'index'])
        ->middleware('organisation.context')->middleware(['kinship.role:owner', 'kinship.permission:organisation_membership.index'])
        ->name('organisations.members.index');
    Route::post('/organisations/{organisation:uuid}/members/invitations', [OrganisationInvitationManagementController::class, 'store'])
        ->middleware(['organisation.context', 'throttle:10,1'])->middleware(['kinship.role:owner', 'kinship.permission:organisation_invitation.store'])
        ->name('organisations.members.invite');
    Route::patch('/organisations/{organisation:uuid}/members/{userId}/role', [OrganisationMembersController::class, 'update'])
        ->middleware('organisation.context')->whereNumber('userId')->middleware(['kinship.role:owner', 'kinship.permission:organisation_membership.update'])
        ->name('organisations.members.change-role');
    Route::delete('/organisations/{organisation:uuid}/members/{userId}', [OrganisationMembersController::class, 'destroy'])
        ->middleware('organisation.context')->whereNumber('userId')->middleware(['kinship.role:owner', 'kinship.permission:organisation_membership.delete'])
        ->name('organisations.members.revoke');
    Route::delete('/organisations/{organisation:uuid}/members/invitations/{uuid}', [OrganisationInvitationManagementController::class, 'destroy'])
        ->middleware('organisation.context')->whereUuid('uuid')->middleware(['kinship.role:owner', 'kinship.permission:organisation_invitation.delete'])
        ->name('organisations.members.cancel-invitation');

    Route::get('/invitations/{token}', [OrganisationInvitationController::class, 'show'])
        ->where('token', '[a-f0-9]{64}')->name('organisation.invitations.show');
    Route::post('/invitations/{token}/accept', AcceptOrganisationInvitationController::class)
        ->where('token', '[a-f0-9]{64}')->middleware('throttle:10,1')->name('organisation.invitations.accept');

    Route::scopeBindings()->group(function (): void {
        Route::get('/organisations/{organisation:uuid}/brands/{brand:uuid}/members', [BrandMembersController::class, 'index'])
            ->middleware('organisation.context')->middleware(['kinship.role:owner,manager', 'kinship.permission:brand_membership.index'])
            ->name('organisations.brands.members.index');
        Route::post('/organisations/{organisation:uuid}/brands/{brand:uuid}/members', [BrandMembersController::class, 'store'])
            ->middleware(['organisation.context', 'throttle:20,1'])->middleware(['kinship.role:owner,manager', 'kinship.permission:brand_membership.store'])
            ->name('organisations.brands.members.add');
        Route::patch('/organisations/{organisation:uuid}/brands/{brand:uuid}/members/{userId}/role', [BrandMembersController::class, 'update'])
            ->middleware('organisation.context')->whereNumber('userId')->middleware(['kinship.role:owner,manager', 'kinship.permission:brand_membership.update'])
            ->name('organisations.brands.members.change-role');
        Route::delete('/organisations/{organisation:uuid}/brands/{brand:uuid}/members/{userId}', [BrandMembersController::class, 'destroy'])
            ->middleware('organisation.context')->whereNumber('userId')->middleware(['kinship.role:owner,manager', 'kinship.permission:brand_membership.delete'])
            ->name('organisations.brands.members.revoke');
    });

    // Normalised marketing strategy. Workspace context and Kinship run before controllers.
    Route::scopeBindings()->middleware('organisation.context')->prefix('organisations/{organisation:uuid}/brands/{brand:uuid}/strategy')
        ->name('organisations.brands.strategy.')->group(function (): void {

            Route::get('/', [BrandStrategyController::class, 'show'])->middleware('kinship.permission:brand_profile.index')->name('show');
            Route::patch('/profile', [BrandProfileController::class, 'update'])->middleware(['kinship.role:owner,manager', 'kinship.permission:brand_profile.update'])->name('profile');
            Route::post('/audiences', [BrandAudienceSegmentController::class, 'store'])->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:brand_audience_segment.store'])->name('audience');
            Route::post('/voices', [BrandVoiceProfileController::class, 'store'])->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:brand_voice_profile.store'])->name('voice');
            Route::patch('/claims/policy', [BrandClaimPolicyController::class, 'update'])->middleware(['kinship.role:owner,manager', 'kinship.permission:brand_claim_policy.update'])->name('claim-policy');
            Route::post('/claims', [BrandClaimController::class, 'store'])->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:brand_claim.store'])->name('claim');
            Route::post('/approval-policies', [BrandApprovalPolicyController::class, 'store'])->middleware(['kinship.role:owner', 'kinship.permission:brand_approval_policy.store'])->name('approval-policy');
            Route::post('/knowledge-sources', [BrandKnowledgeSourceController::class, 'store'])->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:brand_knowledge_source.store'])->name('sources.store');
            Route::post('/knowledge-sources/{sourceUuid}/review', ReviewBrandKnowledgeSourceController::class)->middleware(['kinship.role:owner,manager', 'kinship.permission:knowledge-source.verify'])->name('sources.review');
            Route::post('/claims/{claimUuid}/evidence', [BrandClaimEvidenceController::class, 'store'])->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:brand_claim_evidence.store'])->name('evidence.store');
            Route::post('/evidence/{evidenceUuid}/review', ReviewBrandClaimEvidenceController::class)->middleware(['kinship.role:owner,manager', 'kinship.permission:claim-evidence.verify'])->name('evidence.review');
            Route::post('/claims/{claimUuid}/submit-review', SubmitBrandClaimForReviewController::class)->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:claim.submit-review'])->name('claims.submit-review');
            Route::post('/claims/{claimUuid}/review', ReviewBrandClaimController::class)->middleware(['kinship.role:owner,manager', 'kinship.permission:claim.review'])->name('claims.review');
            Route::post('/approval-policies/{policyUuid}/activate', ActivateBrandApprovalPolicyController::class)->middleware(['kinship.role:owner', 'kinship.permission:approval-policy.activate'])->name('approval-policies.activate');
        });

    // Social OAuth: session-bound initiation; callback has a static provider URI and one-time hashed state.
    Route::scopeBindings()->middleware('organisation.context')
        ->prefix('organisations/{organisation:uuid}/brands/{brand:uuid}')
        ->name('organisations.brands.')->group(function (): void {
            Route::get('social-accounts', [BrandSocialAccountController::class, 'index'])
                ->middleware('kinship.permission:social_account.index')->name('social-accounts.index');
            Route::post('social-connections/{provider}/start', StartSocialOAuthController::class)
                ->whereIn('provider', SocialProviderRegistry::PROVIDERS)
                ->middleware(['kinship.role:owner,manager', 'kinship.permission:social-account.connect', 'throttle:5,1,social-oauth-start:'])
                ->name('social-connections.start');
            Route::get('social-selections/{selectionUuid}', [SocialOAuthSelectionController::class, 'show'])
                ->whereUuid('selectionUuid')
                ->middleware(['kinship.role:owner,manager', 'kinship.permission:social-account.connect'])
                ->name('social-selections.show');
            Route::post('social-selections/{selectionUuid}/confirm', ConfirmSocialOAuthSelectionController::class)
                ->whereUuid('selectionUuid')
                ->middleware(['kinship.role:owner,manager', 'kinship.permission:social-account.connect', 'throttle:5,1,social-oauth-selection-confirm:'])
                ->name('social-selections.confirm');
            Route::post('social-accounts/{accountUuid}/verify-publishing',
                VerifySocialPublishingCapabilityController::class)
                ->whereUuid('accountUuid')->middleware(['kinship.role:owner,manager',
                    'kinship.permission:social-account.connect', 'kinship.permission:approval_request.update', 'throttle:5,1'])
                ->name('social-accounts.verify-publishing');
            Route::delete('social-accounts/{accountUuid}', [BrandSocialAccountController::class, 'destroy'])
                ->whereUuid('accountUuid')
                ->middleware(['kinship.role:owner,manager', 'kinship.permission:social-account.disconnect'])
                ->name('social-accounts.destroy');
        });

    Route::get('integrations/social/{provider}/callback', CompleteSocialOAuthController::class)
        ->whereIn('provider', SocialProviderRegistry::PROVIDERS)->middleware('throttle:15,1,social-oauth-callback:')
        ->name('social.oauth.callback');

    Route::get('/organisations/{organisation:uuid}/brands', BrandDirectoryController::class)
        ->middleware('organisation.context')
        ->middleware(['kinship.permission:brand.index'])
        ->name('organisations.brands.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Anonymous privacy status page, protected by a 48-character unguessable confirmation code.
Route::get('meta/data-deletion/status/{code}', ShowMetaDataDeletionStatusController::class)
    ->where('code', '[A-Za-z0-9]{48}')->middleware('throttle:60,1')->name('meta.data-deletion.status');

Route::get('privacy/data-deletion', ShowPrivacyDeletionInstructionsController::class)
    ->name('privacy.data-deletion.instructions');

// Organisation owners handle privacy exceptions within their own tenant only.
Route::middleware(['auth', 'verified', 'organisation.context', 'kinship.role:owner', 'kinship.permission:privacy-deletion.review'])
    ->prefix('organisations/{organisation:uuid}/privacy')->name('organisations.privacy.')
    ->group(function (): void {
        Route::post('retention-reviews/{followupId}/acknowledge', AcknowledgePrivacyRetentionReviewController::class)
            ->whereNumber('followupId')->middleware('throttle:10,1')->name('retention-reviews.acknowledge');
        Route::get('deletion-reviews', [MetaDeletionReviewController::class, 'index'])
            ->name('deletion-reviews.index');
        Route::post('deletion-reviews/{targetId}/resolve', ResolveMetaDeletionTargetController::class)
            ->whereNumber('targetId')->middleware('throttle:10,1')->name('deletion-reviews.resolve');
    });

// Explicit, two-stage, organisation-owner grant revocation. Local disconnect remains separate.
Route::middleware(['auth', 'verified', 'organisation.context', 'kinship.role:owner', 'kinship.permission:social-grant.revoke'])
    ->scopeBindings()->prefix('organisations/{organisation:uuid}')->name('organisations.grants.')
    ->group(function (): void {
        Route::post('brands/{brand:uuid}/social-accounts/{accountUuid}/grant-revocations',
            [GrantRevocationController::class, 'store'])
            ->whereUuid('accountUuid')->middleware('throttle:5,1')->name('revocations.store');
        Route::post('grant-revocations/{requestUuid}/confirm',
            ConfirmGrantRevocationController::class)
            ->whereUuid('requestUuid')->middleware('throttle:5,1')->name('revocations.confirm');
    });

// Two-person reconciliation desk. Manual attestations never substitute for provider acknowledgements.
Route::middleware(['auth', 'verified', 'organisation.context', 'kinship.role:owner', 'kinship.permission:social-grant.reconcile'])
    ->prefix('organisations/{organisation:uuid}')->name('organisations.grants.')
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

// Draft content studio. Mutations are scoped and permission-checked before controllers.
Route::middleware(['auth', 'verified', 'organisation.context'])
    ->scopeBindings()->prefix('organisations/{organisation:uuid}/brands/{brand:uuid}/content')
    ->name('organisations.brands.content.')->group(function (): void {
        Route::get('/', [ContentStudioController::class, 'index'])
            ->middleware('kinship.permission:content_item.index')->name('index');
        Route::get('{itemUuid}', [ContentStudioController::class, 'show'])
            ->whereUuid('itemUuid')->middleware('kinship.permission:content_item.view')->name('show');
        Route::post('/', [ContentItemController::class, 'store'])
            ->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:content_item.store'])->name('store');
        Route::post('{itemUuid}/versions', [ContentVersionController::class, 'store'])
            ->whereUuid('itemUuid')->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:content_version.store'])->name('versions.store');
        Route::post('media-assets', [MediaAssetController::class, 'store'])
            ->middleware(['kinship.role:owner,manager,contributor', 'kinship.permission:media_asset.store'])->name('media.store');

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

// First-party CSRF-protected, session-only management of scoped read-only integration keys.
// Never expose issuance/revocation via third-party bearer-token endpoints.
Route::middleware(['auth', 'verified', 'organisation.context', 'planner.first_party'])
    ->scopeBindings()
    ->prefix('organisations/{organisation:uuid}/brands/{brand:uuid}/integrations/tokens')
    ->name('organisations.brands.integrations.tokens.')->group(function (): void {
        Route::get('/', [PlannerIntegrationTokenController::class, 'index'])
            ->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update'])
            ->name('index');
        Route::post('/', [PlannerIntegrationTokenController::class, 'store'])
            ->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:3,1'])
            ->name('store');
        Route::delete('{grantId}', [PlannerIntegrationTokenController::class, 'destroy'])
            ->whereNumber('grantId')
            ->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:10,1'])
            ->name('destroy');
    });

// Read-only operator safety counters. No provider IO, no recovery writes.
Route::get('organisations/{organisation:uuid}/brands/{brand:uuid}/operational-safety',
    PlannerOperationalSafetyController::class)
    ->scopeBindings()->middleware(['auth', 'verified', 'organisation.context',
        'kinship.permission:approval_request.index', 'throttle:30,1'])
    ->name('organisations.brands.operational-safety');

// Grant management must be first-party, CSRF-protected and session-only.
Route::middleware(['auth', 'verified', 'organisation.context', 'planner.first_party'])
    ->scopeBindings()->prefix('organisations/{organisation:uuid}/brands/{brand:uuid}/agent/delegations')
    ->name('organisations.brands.agent.delegations.')->group(function (): void {
        Route::get('/', [PlannerAgentDelegationController::class, 'index'])
            ->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.index'])
            ->name('index');
        Route::post('/', [PlannerAgentDelegationController::class, 'store'])
            ->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:3,1'])
            ->name('store');
        Route::delete('/{grantId}', [PlannerAgentDelegationController::class, 'destroy'])
            ->whereNumber('grantId')->middleware(['kinship.role:owner,manager', 'kinship.permission:approval_request.update', 'throttle:10,1'])
            ->name('destroy');
    });

// First-party review of agent-supplied research provenance. Research is never verified automatically.
Route::get('organisations/{organisation:uuid}/brands/{brand:uuid}/agent/work-orders/{uuid}',
    PlannerAgentWorkOrderReviewController::class)
    ->scopeBindings()->whereUuid('uuid')->middleware(['auth', 'verified', 'organisation.context',
        'planner.first_party', 'kinship.role:owner,manager', 'kinship.permission:approval_request.index'])
    ->name('organisations.brands.agent.work-orders.show');

// First-party autonomous research operations are explicitly owned by Planner, not Passport routes.
Route::prefix('organisations/{organisation:uuid}/brands/{brand:uuid}/agent/autonomous-runs')
    ->scopeBindings()->middleware(['auth', 'verified', 'organisation.context', 'planner.first_party'])
    ->name('organisations.brands.agent.autonomous-runs.')->group(function (): void {
        Route::post('/', StartPlannerAutonomousResearchController::class)
            ->middleware(['kinship.role:owner,manager', 'kinship.permission:content_item.store', 'throttle:4,1'])
            ->name('store');
        Route::post('/{uuid}/cancel', CancelPlannerAutonomousResearchController::class)
            ->whereUuid('uuid')->middleware(['kinship.role:owner,manager', 'kinship.permission:content_item.store', 'throttle:6,1'])
            ->name('cancel');
        Route::post('/{uuid}/revise', RevisePlannerAutonomousResourceController::class)
            ->whereUuid('uuid')->middleware(['kinship.role:owner,manager', 'kinship.permission:content_item.store', 'throttle:10,1'])
            ->name('revise');
        Route::post('/{uuid}/promote', PromotePlannerAutonomousResourceController::class)
            ->whereUuid('uuid')->middleware(['kinship.role:owner,manager', 'kinship.permission:content_item.store', 'throttle:6,1'])
            ->name('promote');
    });

// A single unpersisted image returned only to the authenticated operator's browser.
// The returned binary MUST be reviewed and uploaded through Tetranyble Storage before linking to a draft.
Route::post('organisations/{organisation:uuid}/brands/{brand:uuid}/creative/private-preview',
    DownloadPrivateCreativePreviewController::class)
    ->scopeBindings()->middleware(['auth', 'verified', 'organisation.context', 'planner.first_party',
        'kinship.role:owner,manager,contributor', 'kinship.permission:media_asset.store', 'throttle:2,1'])
    ->name('organisations.brands.creative.private-preview');

// Operator-only one-time creative PNG handoff. A matching, independently verified package-owned
// upload is required; no AI-generated image may become a publishable media asset directly.
Route::post('organisations/{organisation:uuid}/brands/{brand:uuid}/creative/handoffs/{handoffUuid}/link',
    FinalizeCreativeImageHandoffController::class)
    ->scopeBindings()->whereUuid('handoffUuid')->middleware(['auth', 'verified', 'organisation.context', 'planner.first_party',
        'kinship.role:owner,manager,contributor', 'kinship.permission:media_asset.store', 'throttle:8,1'])
    ->name('organisations.brands.creative.handoffs.link');
