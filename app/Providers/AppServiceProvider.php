<?php

namespace App\Providers;

use App\Authorization\CurrentOrganisation;
use App\Domain\Analytics\Provider\FacebookPagePostStatsClientContract;
use App\Domain\Analytics\Provider\InstagramMediaStatsClientContract;
use App\Domain\Analytics\Provider\LaravelFacebookPagePostStatsClient;
use App\Domain\Analytics\Provider\LaravelInstagramMediaStatsClient;
use App\Domain\Analytics\Provider\LaravelTikTokVideoStatsClient;
use App\Domain\Analytics\Provider\LaravelXPostStatsClient;
use App\Domain\Analytics\Provider\LaravelYouTubeVideoStatsClient;
use App\Domain\Analytics\Provider\TikTokVideoStatsClientContract;
use App\Domain\Analytics\Provider\XPostStatsClientContract;
use App\Domain\Analytics\Provider\YouTubeVideoStatsClientContract;
use App\Domain\Publishing\Media\MediaDistributionUrlResolverContract;
use App\Domain\Publishing\Media\TetranybleMediaDistributionUrlResolver;
use App\Domain\Publishing\Media\UnavailableMediaDistributionUrlResolver;
use App\Domain\Publishing\YouTube\CanonicalVideoChunkSourceContract;
use App\Domain\Publishing\YouTube\LaravelYouTubeUploadTransport;
use App\Domain\Publishing\YouTube\TetranybleCanonicalVideoChunkSource;
use App\Domain\Publishing\YouTube\UnavailableVideoChunkSource;
use App\Domain\Publishing\YouTube\YouTubeUploadTransportContract;
use App\Repository\Contracts\AiCampaignProposalRepositoryContract;
use App\Repository\Contracts\AiClaimReviewRepositoryContract;
use App\Repository\Contracts\AiSuggestionRepositoryContract;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\BrandAiUsageRepositoryContract;
use App\Repository\Contracts\BrandDirectoryRepositoryContract;
use App\Repository\Contracts\BrandGovernanceRepositoryContract;
use App\Repository\Contracts\BrandMemberRepositoryContract;
use App\Repository\Contracts\BrandOperationsMetricsRepositoryContract;
use App\Repository\Contracts\BrandStrategyRepositoryContract;
use App\Repository\Contracts\BrandWriteRepositoryContract;
use App\Repository\Contracts\CampaignRepositoryContract;
use App\Repository\Contracts\ContentApprovalRepositoryContract;
use App\Repository\Contracts\ContentStudioRepositoryContract;
use App\Repository\Contracts\CreativeImageHandoffRepositoryContract;
use App\Repository\Contracts\MediaPublicationPreparationRepositoryContract;
use App\Repository\Contracts\MediaPublicationRunRepositoryContract;
use App\Repository\Contracts\MediaTikTokConsentRepositoryContract;
use App\Repository\Contracts\MetaCapabilityInspectionRepositoryContract;
use App\Repository\Contracts\MetaDataDeletionRepositoryContract;
use App\Repository\Contracts\MetaDeletionReviewRepositoryContract;
use App\Repository\Contracts\OrganisationDirectoryRepositoryContract;
use App\Repository\Contracts\OrganisationInvitationOutboxRepositoryContract;
use App\Repository\Contracts\OrganisationInvitationRepositoryContract;
use App\Repository\Contracts\OrganisationMemberRepositoryContract;
use App\Repository\Contracts\OrganisationWriteRepositoryContract;
use App\Repository\Contracts\PlannerAgentAutonomyRepositoryContract;
use App\Repository\Contracts\PlannerAgentRepositoryContract;
use App\Repository\Contracts\PlannerAlertMailRepositoryContract;
use App\Repository\Contracts\PlannerIntegrationGrantRepositoryContract;
use App\Repository\Contracts\PlannerOAuthLinkRepositoryContract;
use App\Repository\Contracts\PlannerOperationalAlertRepositoryContract;
use App\Repository\Contracts\PlannerOperationalSafetyRepositoryContract;
use App\Repository\Contracts\PrivacyRetentionFollowupRepositoryContract;
use App\Repository\Contracts\ProviderCredentialRepositoryContract;
use App\Repository\Contracts\ProviderGrantOwnershipRepositoryContract;
use App\Repository\Contracts\ProviderGrantReconciliationRepositoryContract;
use App\Repository\Contracts\ProviderGrantRevocationRepositoryContract;
use App\Repository\Contracts\ProviderMetricSyncPolicyRepositoryContract;
use App\Repository\Contracts\ProviderMetricSyncRepositoryContract;
use App\Repository\Contracts\ProviderTokenLifecycleRepositoryContract;
use App\Repository\Contracts\PublicationDeliveryContextRepositoryContract;
use App\Repository\Contracts\PublicationExecutionRepositoryContract;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use App\Repository\Contracts\PublicationOperationsRepositoryContract;
use App\Repository\Contracts\PublicationReadinessRepositoryContract;
use App\Repository\Contracts\PublicationReconciliationRepositoryContract;
use App\Repository\Contracts\PublicationSandboxRepositoryContract;
use App\Repository\Contracts\RecurringCampaignRepositoryContract;
use App\Repository\Contracts\ReportAutomationRepositoryContract;
use App\Repository\Contracts\SocialConnectionRepositoryContract;
use App\Repository\Contracts\SocialMetricRepositoryContract;
use App\Repository\Contracts\SocialOAuthSelectionRepositoryContract;
use App\Repository\Contracts\SocialPublishCapabilityRepositoryContract;
use App\Repository\Contracts\StorageMediaCandidateRepositoryContract;
use App\Repository\Contracts\UserRepositoryContract;
use App\Repository\Contracts\YouTubeTransferRepositoryContract;
use App\Repository\Eloquent\AiCampaignProposalRepository;
use App\Repository\Eloquent\AiClaimReviewRepository;
use App\Repository\Eloquent\AiSuggestionRepository;
use App\Repository\Eloquent\AuditEventRepository;
use App\Repository\Eloquent\BrandAiUsageRepository;
use App\Repository\Eloquent\BrandDirectoryRepository;
use App\Repository\Eloquent\BrandGovernanceRepository;
use App\Repository\Eloquent\BrandMemberRepository;
use App\Repository\Eloquent\BrandOperationsMetricsRepository;
use App\Repository\Eloquent\BrandStrategyRepository;
use App\Repository\Eloquent\BrandWriteRepository;
use App\Repository\Eloquent\CampaignRepository;
use App\Repository\Eloquent\ContentApprovalRepository;
use App\Repository\Eloquent\ContentStudioRepository;
use App\Repository\Eloquent\CreativeImageHandoffRepository;
use App\Repository\Eloquent\EncryptedProviderCredentialRepository;
use App\Repository\Eloquent\MediaPublicationPreparationRepository;
use App\Repository\Eloquent\MediaPublicationRunRepository;
use App\Repository\Eloquent\MediaTikTokConsentRepository;
use App\Repository\Eloquent\MetaCapabilityInspectionRepository;
use App\Repository\Eloquent\MetaDataDeletionRepository;
use App\Repository\Eloquent\MetaDeletionReviewRepository;
use App\Repository\Eloquent\OrganisationDirectoryRepository;
use App\Repository\Eloquent\OrganisationInvitationOutboxRepository;
use App\Repository\Eloquent\OrganisationInvitationRepository;
use App\Repository\Eloquent\OrganisationMemberRepository;
use App\Repository\Eloquent\OrganisationWriteRepository;
use App\Repository\Eloquent\PlannerAgentAutonomyRepository;
use App\Repository\Eloquent\PlannerAgentRepository;
use App\Repository\Eloquent\PlannerAlertMailRepository;
use App\Repository\Eloquent\PlannerIntegrationGrantRepository;
use App\Repository\Eloquent\PlannerOAuthLinkRepository;
use App\Repository\Eloquent\PlannerOperationalAlertRepository;
use App\Repository\Eloquent\PlannerOperationalSafetyRepository;
use App\Repository\Eloquent\PrivacyRetentionFollowupRepository;
use App\Repository\Eloquent\ProviderGrantOwnershipRepository;
use App\Repository\Eloquent\ProviderGrantReconciliationRepository;
use App\Repository\Eloquent\ProviderGrantRevocationRepository;
use App\Repository\Eloquent\ProviderMetricSyncPolicyRepository;
use App\Repository\Eloquent\ProviderMetricSyncRepository;
use App\Repository\Eloquent\ProviderTokenLifecycleRepository;
use App\Repository\Eloquent\PublicationDeliveryContextRepository;
use App\Repository\Eloquent\PublicationExecutionRepository;
use App\Repository\Eloquent\PublicationIntentRepository;
use App\Repository\Eloquent\PublicationOperationsRepository;
use App\Repository\Eloquent\PublicationReadinessRepository;
use App\Repository\Eloquent\PublicationReconciliationRepository;
use App\Repository\Eloquent\PublicationSandboxRepository;
use App\Repository\Eloquent\RecurringCampaignRepository;
use App\Repository\Eloquent\ReportAutomationRepository;
use App\Repository\Eloquent\SocialConnectionRepository;
use App\Repository\Eloquent\SocialMetricRepository;
use App\Repository\Eloquent\SocialOAuthSelectionRepository;
use App\Repository\Eloquent\SocialPublishCapabilityRepository;
use App\Repository\Eloquent\StorageMediaCandidateRepository;
use App\Repository\Eloquent\UserRepository;
use App\Repository\Eloquent\YouTubeTransferRepository;
use App\Services\OAuth\ManagePlannerOAuthLinks;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Passport owns the OAuth2 engine; Planner owns EVERY HTTP route.
        // No package-generated hidden OAuth endpoints are permitted.
        Passport::ignoreRoutes();
        $this->app->bind(RecurringCampaignRepositoryContract::class,
            RecurringCampaignRepository::class);
        $this->app->bind(CreativeImageHandoffRepositoryContract::class,
            CreativeImageHandoffRepository::class);
        $this->app->bind(PlannerOAuthLinkRepositoryContract::class,
            PlannerOAuthLinkRepository::class);
        $this->app->bind(PlannerAgentAutonomyRepositoryContract::class,
            PlannerAgentAutonomyRepository::class);
        $this->app->bind(PlannerAgentRepositoryContract::class,
            PlannerAgentRepository::class);
        $this->app->bind(PlannerOperationalAlertRepositoryContract::class,
            PlannerOperationalAlertRepository::class);
        $this->app->bind(PlannerAlertMailRepositoryContract::class,
            PlannerAlertMailRepository::class);
        $this->app->bind(PlannerOperationalSafetyRepositoryContract::class,
            PlannerOperationalSafetyRepository::class);
        $this->app->bind(PlannerIntegrationGrantRepositoryContract::class,
            PlannerIntegrationGrantRepository::class);
        $this->app->scoped(CurrentOrganisation::class);
        $this->app->bind(SocialPublishCapabilityRepositoryContract::class,
            SocialPublishCapabilityRepository::class);
        $this->app->bind(ReportAutomationRepositoryContract::class, ReportAutomationRepository::class);
        $this->app->bind(SocialMetricRepositoryContract::class, SocialMetricRepository::class);
        $this->app->bind(ProviderMetricSyncRepositoryContract::class, ProviderMetricSyncRepository::class);
        $this->app->bind(ProviderMetricSyncPolicyRepositoryContract::class, ProviderMetricSyncPolicyRepository::class);
        $this->app->bind(YouTubeVideoStatsClientContract::class, LaravelYouTubeVideoStatsClient::class);
        $this->app->bind(XPostStatsClientContract::class, LaravelXPostStatsClient::class);
        $this->app->bind(FacebookPagePostStatsClientContract::class, LaravelFacebookPagePostStatsClient::class);
        $this->app->bind(TikTokVideoStatsClientContract::class, LaravelTikTokVideoStatsClient::class);
        $this->app->bind(InstagramMediaStatsClientContract::class, LaravelInstagramMediaStatsClient::class);
        $this->app->bind(BrandAiUsageRepositoryContract::class,
            BrandAiUsageRepository::class);
        $this->app->bind(AiSuggestionRepositoryContract::class, AiSuggestionRepository::class);
        $this->app->bind(AiCampaignProposalRepositoryContract::class, AiCampaignProposalRepository::class);
        $this->app->bind(BrandOperationsMetricsRepositoryContract::class, BrandOperationsMetricsRepository::class);
        $this->app->bind(ContentApprovalRepositoryContract::class, ContentApprovalRepository::class);
        $this->app->bind(PublicationReadinessRepositoryContract::class, PublicationReadinessRepository::class);
        $this->app->bind(PublicationOperationsRepositoryContract::class, PublicationOperationsRepository::class);
        $this->app->bind(MediaPublicationPreparationRepositoryContract::class, MediaPublicationPreparationRepository::class);
        $this->app->bind(MediaPublicationRunRepositoryContract::class, MediaPublicationRunRepository::class);
        $this->app->bind(MediaTikTokConsentRepositoryContract::class, MediaTikTokConsentRepository::class);
        $this->app->bind(AiClaimReviewRepositoryContract::class,
            AiClaimReviewRepository::class);
        $this->app->bind(StorageMediaCandidateRepositoryContract::class,
            StorageMediaCandidateRepository::class);
        $this->app->bind(YouTubeTransferRepositoryContract::class,
            YouTubeTransferRepository::class);
        $this->app->bind(CanonicalVideoChunkSourceContract::class,
            (bool) config('planner.storage_v3_adapter_verified', false)
                ? TetranybleCanonicalVideoChunkSource::class
                : UnavailableVideoChunkSource::class);
        $this->app->bind(YouTubeUploadTransportContract::class,
            LaravelYouTubeUploadTransport::class);
        $this->app->bind(MediaDistributionUrlResolverContract::class,
            (bool) config('planner.storage_v3_adapter_verified', false)
                ? TetranybleMediaDistributionUrlResolver::class
                : UnavailableMediaDistributionUrlResolver::class);
        $this->app->bind(PublicationExecutionRepositoryContract::class, PublicationExecutionRepository::class);
        $this->app->bind(PublicationDeliveryContextRepositoryContract::class, PublicationDeliveryContextRepository::class);
        $this->app->bind(PublicationReconciliationRepositoryContract::class, PublicationReconciliationRepository::class);
        $this->app->bind(PublicationIntentRepositoryContract::class, PublicationIntentRepository::class);
        $this->app->bind(PublicationSandboxRepositoryContract::class, PublicationSandboxRepository::class);
        $this->app->bind(ContentStudioRepositoryContract::class, ContentStudioRepository::class);
        $this->app->bind(ProviderGrantRevocationRepositoryContract::class, ProviderGrantRevocationRepository::class);
        $this->app->bind(ProviderGrantReconciliationRepositoryContract::class, ProviderGrantReconciliationRepository::class);
        $this->app->bind(PrivacyRetentionFollowupRepositoryContract::class, PrivacyRetentionFollowupRepository::class);
        $this->app->bind(MetaCapabilityInspectionRepositoryContract::class, MetaCapabilityInspectionRepository::class);
        $this->app->bind(ProviderGrantOwnershipRepositoryContract::class, ProviderGrantOwnershipRepository::class);
        $this->app->bind(MetaDeletionReviewRepositoryContract::class, MetaDeletionReviewRepository::class);
        $this->app->bind(MetaDataDeletionRepositoryContract::class,
            MetaDataDeletionRepository::class);
        $this->app->bind(SocialOAuthSelectionRepositoryContract::class,
            SocialOAuthSelectionRepository::class);
        $this->app->bind(SocialConnectionRepositoryContract::class,
            SocialConnectionRepository::class);
        $this->app->bind(ProviderTokenLifecycleRepositoryContract::class,
            ProviderTokenLifecycleRepository::class);
        $this->app->bind(ProviderCredentialRepositoryContract::class,
            EncryptedProviderCredentialRepository::class);
        $this->app->bind(BrandGovernanceRepositoryContract::class,
            BrandGovernanceRepository::class);
        $this->app->bind(BrandStrategyRepositoryContract::class, BrandStrategyRepository::class);
        $this->app->bind(BrandMemberRepositoryContract::class, BrandMemberRepository::class);
        $this->app->bind(OrganisationMemberRepositoryContract::class, OrganisationMemberRepository::class);
        $this->app->bind(OrganisationInvitationOutboxRepositoryContract::class, OrganisationInvitationOutboxRepository::class);
        $this->app->bind(OrganisationInvitationRepositoryContract::class, OrganisationInvitationRepository::class);
        $this->app->bind(OrganisationWriteRepositoryContract::class, OrganisationWriteRepository::class);
        $this->app->bind(BrandWriteRepositoryContract::class, BrandWriteRepository::class);
        $this->app->bind(AuditEventRepositoryContract::class, AuditEventRepository::class);
        $this->app->bind(UserRepositoryContract::class, UserRepository::class);
        $this->app->bind(CampaignRepositoryContract::class, CampaignRepository::class);
        $this->app->bind(OrganisationDirectoryRepositoryContract::class, OrganisationDirectoryRepository::class);
        $this->app->bind(BrandDirectoryRepositoryContract::class, BrandDirectoryRepository::class);
    }

    public function boot(): void
    {
        Passport::tokensCan(['mcp:use' => 'Access authorised Planner MCP tools']);
        Passport::tokensExpireIn(now()->addMinutes(60));
        Passport::refreshTokensExpireIn(now()->addDays(7));
        Passport::authorizationView(function (array $parameters) {
            $actor = $parameters['user'];
            $client = $parameters['client'];
            request()->session()->put('planner_oauth_client_id', (string) $client->getKey());

            return view('oauth.planner-agent-consent', [
                ...$parameters,
                'delegations' => app(ManagePlannerOAuthLinks::class)
                    ->availableDelegations($actor),
            ]);
        });

        Vite::prefetch(concurrency: 3);
    }
}
