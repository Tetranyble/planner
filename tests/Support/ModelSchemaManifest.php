<?php

// Review this manifest when adding a migration or a model.
// It is generated from first-party migrations, including Unipotence.
return [
    'AiActionApproval' => [
        'table' => 'ai_action_approvals',
        'columns' => ['action_type', 'ai_tool_execution_id', 'arguments_hash', 'brand_id', 'created_at', 'decided_at', 'decided_by_user_id', 'expires_at', 'id', 'organisation_id', 'reason', 'requested_at', 'status', 'updated_at', 'uuid'],
        'relations' => ['aiToolExecution' => ['AiToolExecution', 'ai_tool_execution_id'], 'brand' => ['Brand', 'brand_id'], 'decidedByUser' => ['User', 'decided_by_user_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'AiCampaignProposal' => [
        'table' => 'ai_campaign_proposals',
        'columns' => ['audience', 'brand_id', 'created_at', 'duration_days', 'id', 'model', 'objective', 'organisation_id', 'platforms', 'prompt_hash', 'proposal_text', 'provider_evidence_hash', 'provider_evidence_snapshot', 'requested_by_user_id', 'result_hash', 'review_note', 'reviewed_at', 'reviewed_by_user_id', 'status', 'title', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'requestedByUser' => ['User', 'requested_by_user_id'], 'reviewedByUser' => ['User', 'reviewed_by_user_id']],
    ],
    'AiCampaignProposalDraft' => [
        'table' => 'ai_campaign_proposal_drafts',
        'columns' => ['ai_campaign_proposal_id', 'approved_proposal_hash', 'brand_id', 'content_item_id', 'created_at', 'created_by_user_id', 'id', 'organisation_id', 'updated_at'],
        'relations' => ['aiCampaignProposal' => ['AiCampaignProposal', 'ai_campaign_proposal_id'], 'brand' => ['Brand', 'brand_id'], 'contentItem' => ['ContentItem', 'content_item_id'], 'createdByUser' => ['User', 'created_by_user_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'AiContentSuggestion' => [
        'table' => 'ai_content_suggestions',
        'columns' => ['brand_id', 'campaign_name', 'created_at', 'id', 'model', 'organisation_id', 'platform', 'promoted_at', 'promoted_by_user_id', 'promoted_content_item_id', 'prompt_hash', 'requested_by_user_id', 'result_hash', 'status', 'suggestion_text', 'topic', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'promotedByUser' => ['User', 'promoted_by_user_id'], 'promotedContentItem' => ['ContentItem', 'promoted_content_item_id'], 'requestedByUser' => ['User', 'requested_by_user_id']],
    ],
    'AiModelUsage' => [
        'table' => 'ai_model_usages',
        'columns' => ['ai_run_id', 'amount_minor', 'brand_id', 'created_at', 'currency', 'id', 'input_tokens', 'invocation_key', 'model_name', 'model_provider', 'organisation_id', 'output_tokens', 'recorded_at', 'updated_at'],
        'relations' => ['aiRun' => ['AiRun', 'ai_run_id'], 'brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'AiRun' => [
        'table' => 'ai_runs',
        'columns' => ['automation_run_id', 'brand_id', 'created_at', 'error_code', 'finished_at', 'id', 'input_ref', 'lease_owner', 'lease_until', 'model_name', 'model_provider', 'organisation_id', 'output_ref', 'purpose', 'started_at', 'status', 'updated_at', 'uuid'],
        'relations' => ['automationRun' => ['AutomationRun', 'automation_run_id'], 'brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'AiRunCheckpoint' => [
        'table' => 'ai_run_checkpoints',
        'columns' => ['ai_run_id', 'brand_id', 'checkpoint_kind', 'created_at', 'id', 'organisation_id', 'recorded_at', 'sequence_number', 'state_hash', 'state_ref', 'updated_at', 'uuid'],
        'relations' => ['aiRun' => ['AiRun', 'ai_run_id'], 'brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'AiToolAttempt' => [
        'table' => 'ai_tool_attempts',
        'columns' => ['ai_tool_execution_id', 'attempt_number', 'brand_id', 'created_at', 'error_code', 'finished_at', 'id', 'organisation_id', 'outcome', 'response_ref', 'started_at', 'updated_at'],
        'relations' => ['aiToolExecution' => ['AiToolExecution', 'ai_tool_execution_id'], 'brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'AiToolExecution' => [
        'table' => 'ai_tool_executions',
        'columns' => ['ai_run_id', 'brand_id', 'created_at', 'error_code', 'finished_at', 'id', 'input_hash', 'operation_key', 'organisation_id', 'result_ref', 'started_at', 'status', 'tool_name', 'updated_at'],
        'relations' => ['aiRun' => ['AiRun', 'ai_run_id'], 'brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'AnalyticRollup' => [
        'table' => 'analytic_rollups',
        'columns' => ['brand_id', 'campaign_id', 'computed_at', 'created_at', 'id', 'metric_key', 'metric_value', 'organisation_id', 'period_grain', 'period_start', 'social_account_id', 'unique_key', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'campaign' => ['Campaign', 'campaign_id'], 'organisation' => ['Organisation', 'organisation_id'], 'socialAccount' => ['SocialAccount', 'social_account_id']],
    ],
    'AnalyticSnapshot' => [
        'table' => 'analytic_snapshots',
        'columns' => ['brand_id', 'collection_status', 'created_at', 'id', 'metric_scope', 'metrics', 'observed_at', 'organisation_id', 'provider_as_of', 'published_post_id', 'social_account_id', 'unique_key', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'publishedPost' => ['PublishedPost', 'published_post_id'], 'socialAccount' => ['SocialAccount', 'social_account_id']],
    ],
    'ApiLog' => [
        'table' => 'api_logs',
        'columns' => ['correlation_id', 'created_at', 'duration_ms', 'error_code', 'http_status', 'id', 'occurred_at', 'operation', 'organisation_id', 'provider', 'rate_limit_class', 'social_account_id', 'updated_at', 'uuid'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id'], 'socialAccount' => ['SocialAccount', 'social_account_id']],
    ],
    'ApiRateLimitBucket' => [
        'table' => 'api_rate_limit_buckets',
        'columns' => ['bucket_key', 'created_at', 'id', 'last_observed_at', 'limit_count', 'organisation_id', 'provider', 'remaining_count', 'reset_at', 'updated_at'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id']],
    ],
    'ApprovalDecision' => [
        'table' => 'approval_decisions',
        'columns' => ['approval_request_id', 'approver_user_id', 'brand_id', 'comment', 'created_at', 'decided_at', 'decision', 'id', 'organisation_id', 'updated_at'],
        'relations' => ['approvalRequest' => ['ApprovalRequest', 'approval_request_id'], 'approverUser' => ['User', 'approver_user_id'], 'brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'ApprovalRequest' => [
        'table' => 'approval_requests',
        'columns' => ['approval_policy_id', 'brand_id', 'content_target_id', 'created_at', 'expires_at', 'id', 'matched_rule_id', 'organisation_id', 'payload_hash', 'pending_target_marker', 'policy_snapshot', 'request_kind', 'requested_by_user_id', 'required_approvals', 'resolved_at', 'status', 'updated_at'],
        'relations' => ['approvalPolicy' => ['BrandApprovalPolicy', 'approval_policy_id'], 'brand' => ['Brand', 'brand_id'], 'contentTarget' => ['ContentTarget', 'content_target_id'], 'matchedRule' => ['BrandApprovalRule', 'matched_rule_id'], 'organisation' => ['Organisation', 'organisation_id'], 'requestedByUser' => ['User', 'requested_by_user_id']],
    ],
    'AttributionTouch' => [
        'table' => 'attribution_touches',
        'columns' => ['brand_id', 'context', 'created_at', 'id', 'occurred_at', 'organisation_id', 'referrer_domain', 'touch_kind', 'tracking_link_id', 'updated_at', 'uuid', 'visitor_key'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'trackingLink' => ['TrackingLink', 'tracking_link_id']],
    ],
    'AuditLog' => [
        'table' => 'audit_logs',
        'columns' => ['action', 'actor_id', 'actor_kind', 'brand_ref', 'created_at', 'details', 'id', 'ip_hash', 'occurred_at', 'organisation_id', 'request_id', 'resource_id', 'resource_kind', 'updated_at'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id']],
    ],
    'AutomationRule' => [
        'table' => 'automation_rules',
        'columns' => ['brand_id', 'created_at', 'id', 'max_runs_per_day', 'name', 'organisation_id', 'owner_user_id', 'status', 'trigger_kind', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'ownerUser' => ['User', 'owner_user_id']],
    ],
    'AutomationRuleVersion' => [
        'table' => 'automation_rule_versions',
        'columns' => ['action_plan', 'automation_rule_id', 'brand_id', 'condition_config', 'config_hash', 'created_at', 'created_by_user_id', 'id', 'organisation_id', 'policy_snapshot', 'trigger_config', 'updated_at', 'uuid', 'version_number'],
        'relations' => ['automationRule' => ['AutomationRule', 'automation_rule_id'], 'brand' => ['Brand', 'brand_id'], 'createdByUser' => ['User', 'created_by_user_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'AutomationRun' => [
        'table' => 'automation_runs',
        'columns' => ['automation_rule_version_id', 'brand_id', 'created_at', 'error_code', 'finished_at', 'id', 'lease_owner', 'lease_until', 'organisation_id', 'result_summary', 'started_at', 'status', 'trigger_event_key', 'updated_at', 'uuid'],
        'relations' => ['automationRuleVersion' => ['AutomationRuleVersion', 'automation_rule_version_id'], 'brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'Brand' => [
        'table' => 'brands',
        'columns' => ['created_at', 'id', 'name', 'organisation_id', 'settings', 'status', 'timezone', 'updated_at', 'uuid', 'website_url'],
        'relations' => [],
    ],
    'BrandAiUsageReservation' => [
        'table' => 'brand_ai_usage_reservations',
        'columns' => ['brand_id', 'completed_at', 'cost_rate_revision', 'created_at', 'estimated_micro_usd', 'expires_at', 'id', 'model', 'organisation_id', 'output_hash', 'provider_input_tokens', 'provider_output_tokens', 'purpose', 'requested_by_user_id', 'status', 'updated_at', 'usage_day', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'requestedByUser' => ['User', 'requested_by_user_id']],
    ],
    'BrandApprovalPolicy' => [
        'table' => 'brand_approval_policies',
        'columns' => ['activated_at', 'activated_by_user_id', 'active_marker', 'brand_id', 'created_at', 'created_by_user_id', 'default_required_approvals', 'description', 'external_publishing_requires_approval', 'forbid_self_approval', 'id', 'organisation_id', 'policy_version', 'retired_at', 'state', 'updated_at', 'uuid'],
        'relations' => ['activatedByUser' => ['User', 'activated_by_user_id'], 'brand' => ['Brand', 'brand_id'], 'createdByUser' => ['User', 'created_by_user_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'BrandApprovalRule' => [
        'table' => 'brand_approval_rules',
        'columns' => ['action_kind', 'ai_generated', 'brand_id', 'content_kind', 'created_at', 'forbid_self_approval', 'id', 'includes_sensitive_claim', 'is_enabled', 'organisation_id', 'platform', 'policy_id', 'priority', 'required_approvals', 'requires_human_approval', 'rule_name', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'policy' => ['BrandApprovalPolicy', 'policy_id']],
    ],
    'BrandApprovalRuleRole' => [
        'table' => 'brand_approval_rule_roles',
        'columns' => ['approval_rule_id', 'brand_id', 'created_at', 'id', 'organisation_id', 'reviewer_role_id', 'updated_at', 'uuid'],
        'relations' => ['approvalRule' => ['BrandApprovalRule', 'approval_rule_id'], 'brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'BrandAudienceGoal' => [
        'table' => 'brand_audience_goals',
        'columns' => ['audience_segment_id', 'brand_id', 'created_at', 'description', 'id', 'organisation_id', 'priority', 'updated_at', 'uuid'],
        'relations' => ['audienceSegment' => ['BrandAudienceSegment', 'audience_segment_id'], 'brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'BrandAudienceLocation' => [
        'table' => 'brand_audience_locations',
        'columns' => ['audience_segment_id', 'brand_id', 'city_id', 'country_id', 'created_at', 'id', 'organisation_id', 'state_id', 'updated_at', 'uuid'],
        'relations' => ['audienceSegment' => ['BrandAudienceSegment', 'audience_segment_id'], 'brand' => ['Brand', 'brand_id'], 'city' => ['City', 'city_id'], 'country' => ['Country', 'country_id'], 'organisation' => ['Organisation', 'organisation_id'], 'state' => ['State', 'state_id']],
    ],
    'BrandAudiencePainPoint' => [
        'table' => 'brand_audience_pain_points',
        'columns' => ['audience_segment_id', 'brand_id', 'created_at', 'description', 'id', 'organisation_id', 'priority', 'updated_at', 'uuid'],
        'relations' => ['audienceSegment' => ['BrandAudienceSegment', 'audience_segment_id'], 'brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'BrandAudienceRole' => [
        'table' => 'brand_audience_roles',
        'columns' => ['audience_segment_id', 'brand_id', 'created_at', 'decision_role', 'id', 'organisation_id', 'role', 'updated_at', 'uuid'],
        'relations' => ['audienceSegment' => ['BrandAudienceSegment', 'audience_segment_id'], 'brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'BrandAudienceSegment' => [
        'table' => 'brand_audience_segments',
        'columns' => ['audience_kind', 'brand_id', 'created_at', 'description', 'id', 'name', 'organisation_id', 'priority', 'status', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'BrandClaim' => [
        'table' => 'brand_claims',
        'columns' => ['approved_at', 'approved_by_user_id', 'brand_id', 'claim_category', 'claim_text', 'created_at', 'created_by_user_id', 'id', 'organisation_id', 'status', 'updated_at', 'uuid', 'valid_from', 'valid_until'],
        'relations' => ['approvedByUser' => ['User', 'approved_by_user_id'], 'brand' => ['Brand', 'brand_id'], 'createdByUser' => ['User', 'created_by_user_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'BrandClaimDisclaimer' => [
        'table' => 'brand_claim_disclaimers',
        'columns' => ['brand_id', 'claim_id', 'created_at', 'disclaimer_text', 'id', 'locale', 'organisation_id', 'required', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'claim' => ['BrandClaim', 'claim_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'BrandClaimEvidence' => [
        'table' => 'brand_claim_evidence',
        'columns' => ['brand_id', 'claim_id', 'created_at', 'id', 'knowledge_source_id', 'organisation_id', 'review_state', 'reviewed_at', 'reviewed_by_user_id', 'source_locator', 'submitted_by_user_id', 'supporting_excerpt', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'claim' => ['BrandClaim', 'claim_id'], 'organisation' => ['Organisation', 'organisation_id'], 'reviewedByUser' => ['User', 'reviewed_by_user_id'], 'submittedByUser' => ['User', 'submitted_by_user_id']],
    ],
    'BrandClaimPolicy' => [
        'table' => 'brand_claim_policies',
        'columns' => ['allow_guaranteed_outcome_claims', 'approved_claims_only', 'brand_id', 'created_at', 'evidence_required_for_comparisons', 'evidence_required_for_quantified', 'id', 'organisation_id', 'require_review_for_new_claims', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'BrandClaimRestriction' => [
        'table' => 'brand_claim_restrictions',
        'columns' => ['active', 'brand_id', 'created_at', 'explanation', 'id', 'organisation_id', 'restricted_text', 'restriction_kind', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'BrandClaimReviewEvent' => [
        'table' => 'brand_claim_review_events',
        'columns' => ['brand_id', 'claim_hash', 'claim_id', 'created_at', 'decision', 'id', 'organisation_id', 'rationale', 'reviewer_user_id', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'claim' => ['BrandClaim', 'claim_id'], 'organisation' => ['Organisation', 'organisation_id'], 'reviewerUser' => ['User', 'reviewer_user_id']],
    ],
    'BrandClaimReviewEvidence' => [
        'table' => 'brand_claim_review_evidence',
        'columns' => ['brand_id', 'claim_evidence_id', 'created_at', 'excerpt_hash', 'id', 'organisation_id', 'review_event_id', 'source_content_hash', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'claimEvidence' => ['BrandClaimEvidence', 'claim_evidence_id'], 'organisation' => ['Organisation', 'organisation_id'], 'reviewEvent' => ['BrandClaimReviewEvent', 'review_event_id']],
    ],
    'BrandKnowledgeSource' => [
        'table' => 'brand_knowledge_sources',
        'columns' => ['brand_id', 'content_hash', 'content_text', 'id', 'object_key', 'organisation_id', 'retrieved_at', 'source_type', 'source_url', 'submitted_by_user_id', 'title', 'trust_status', 'uuid', 'verified_at', 'verified_by_user_id'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'submittedByUser' => ['User', 'submitted_by_user_id'], 'verifiedByUser' => ['User', 'verified_by_user_id']],
    ],
    'BrandMembership' => [
        'table' => 'brand_memberships',
        'columns' => ['brand_id', 'created_at', 'organisation_id', 'role_id', 'state', 'updated_at', 'user_id'],
        'relations' => [],
    ],
    'BrandProfile' => [
        'table' => 'brand_profiles',
        'columns' => ['brand_id', 'created_at', 'default_locale', 'id', 'organisation_id', 'positioning', 'updated_at', 'updated_by_user_id', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'updatedByUser' => ['User', 'updated_by_user_id']],
    ],
    'BrandVoiceExample' => [
        'table' => 'brand_voice_examples',
        'columns' => ['brand_id', 'created_at', 'example_kind', 'example_text', 'explanation', 'id', 'organisation_id', 'updated_at', 'uuid', 'voice_profile_id'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'voiceProfile' => ['BrandVoiceProfile', 'voice_profile_id']],
    ],
    'BrandVoiceProfile' => [
        'table' => 'brand_voice_profiles',
        'columns' => ['active', 'brand_id', 'created_at', 'directness', 'formality', 'id', 'is_default', 'locale', 'name', 'organisation_id', 'tone_description', 'updated_at', 'uuid', 'warmth'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'BrandVoiceRule' => [
        'table' => 'brand_voice_rules',
        'columns' => ['brand_id', 'created_at', 'enforcement', 'id', 'instruction', 'is_active', 'organisation_id', 'platform', 'priority', 'rule_kind', 'updated_at', 'uuid', 'voice_profile_id'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'voiceProfile' => ['BrandVoiceProfile', 'voice_profile_id']],
    ],
    'BrandVoiceTerm' => [
        'table' => 'brand_voice_terms',
        'columns' => ['brand_id', 'created_at', 'id', 'organisation_id', 'rationale', 'replacement_term', 'term', 'updated_at', 'usage', 'uuid', 'voice_profile_id'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'voiceProfile' => ['BrandVoiceProfile', 'voice_profile_id']],
    ],
    'Campaign' => [
        'table' => 'campaigns',
        'columns' => ['brand_id', 'brief', 'budget_minor', 'created_at', 'currency', 'ends_at', 'id', 'name', 'objective', 'organisation_id', 'owner_user_id', 'starts_at', 'status', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'ownerUser' => ['User', 'owner_user_id']],
    ],
    'CampaignGoal' => [
        'table' => 'campaign_goals',
        'columns' => ['brand_id', 'campaign_id', 'created_at', 'due_at', 'id', 'metric_key', 'organisation_id', 'target_value', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'campaign' => ['Campaign', 'campaign_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'City' => [
        'table' => 'cities',
        'columns' => ['country_code', 'country_id', 'country_name', 'created_at', 'deleted_at', 'id', 'latitude', 'longitude', 'name', 'state_code', 'state_id', 'state_name', 'updated_at', 'wikiDataId'],
        'relations' => [],
    ],
    'ConsentRecord' => [
        'table' => 'consent_records',
        'columns' => ['brand_id', 'channel', 'consent_state', 'created_at', 'evidence_ref', 'id', 'lead_contact_id', 'legal_basis', 'organisation_id', 'purpose', 'recorded_at', 'source_kind', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'leadContact' => ['LeadContact', 'lead_contact_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'ContentItem' => [
        'table' => 'content_items',
        'columns' => ['brand_id', 'campaign_id', 'content_kind', 'content_series_id', 'created_at', 'created_by_user_id', 'id', 'organisation_id', 'state', 'title', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'campaign' => ['Campaign', 'campaign_id'], 'contentSeries' => ['ContentSeries', 'content_series_id'], 'createdByUser' => ['User', 'created_by_user_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'ContentItemLabel' => [
        'table' => 'content_item_labels',
        'columns' => ['brand_id', 'content_item_id', 'content_label_id', 'created_at', 'id', 'organisation_id', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'contentItem' => ['ContentItem', 'content_item_id'], 'contentLabel' => ['ContentLabel', 'content_label_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'ContentLabel' => [
        'table' => 'content_labels',
        'columns' => ['brand_id', 'color_hex', 'created_at', 'id', 'label', 'organisation_id', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'ContentSeries' => [
        'table' => 'content_series',
        'columns' => ['brand_id', 'created_at', 'id', 'name', 'organisation_id', 'status', 'summary', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'ContentTarget' => [
        'table' => 'content_targets',
        'columns' => ['brand_id', 'content_version_id', 'created_at', 'created_by_user_id', 'id', 'organisation_id', 'payload_hash', 'payload_json', 'platform_caption', 'publishing_mode', 'social_account_id', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'contentVersion' => ['ContentVersion', 'content_version_id'], 'createdByUser' => ['User', 'created_by_user_id'], 'organisation' => ['Organisation', 'organisation_id'], 'socialAccount' => ['SocialAccount', 'social_account_id']],
    ],
    'ContentTargetMedia' => [
        'table' => 'content_target_media',
        'columns' => ['brand_id', 'content_target_id', 'created_at', 'id', 'media_variant_id', 'organisation_id', 'role', 'sort_order', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'contentTarget' => ['ContentTarget', 'content_target_id'], 'mediaVariant' => ['MediaVariant', 'media_variant_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'ContentVersion' => [
        'table' => 'content_versions',
        'columns' => ['ai_generated', 'body_json', 'brand_id', 'content_hash', 'content_item_id', 'copy_text', 'created_at', 'created_by_user_id', 'id', 'includes_sensitive_claim', 'organisation_id', 'source_ai_run_id', 'updated_at', 'uuid', 'version_number'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'contentItem' => ['ContentItem', 'content_item_id'], 'createdByUser' => ['User', 'created_by_user_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'ContentVersionMedia' => [
        'table' => 'content_version_media',
        'columns' => ['brand_id', 'content_version_id', 'created_at', 'id', 'media_asset_id', 'organisation_id', 'role', 'sort_order', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'contentVersion' => ['ContentVersion', 'content_version_id'], 'mediaAsset' => ['MediaAsset', 'media_asset_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'ConversionEvent' => [
        'table' => 'conversion_events',
        'columns' => ['brand_id', 'campaign_id', 'created_at', 'currency', 'event_type', 'id', 'lead_id', 'occurred_at', 'organisation_id', 'properties', 'received_at', 'source_event_id', 'source_system', 'tracking_link_id', 'updated_at', 'uuid', 'value_minor'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'campaign' => ['Campaign', 'campaign_id'], 'lead' => ['Lead', 'lead_id'], 'organisation' => ['Organisation', 'organisation_id'], 'trackingLink' => ['TrackingLink', 'tracking_link_id']],
    ],
    'Country' => [
        'table' => 'countries',
        'columns' => ['capital', 'created_at', 'currency', 'currency_name', 'currency_symbol', 'deleted_at', 'emoji', 'emojiU', 'id', 'iso2', 'iso3', 'latitude', 'longitude', 'name', 'native', 'numeric_code', 'phone_code', 'region', 'subregion', 'tld', 'updated_at'],
        'relations' => [],
    ],
    'CreativeImageHandoff' => [
        'table' => 'creative_image_handoffs',
        'columns' => ['actor_user_id', 'brand_id', 'byte_size', 'created_at', 'expires_at', 'height', 'id', 'linked_at', 'media_asset_id', 'mime_type', 'organisation_id', 'sha256', 'state', 'storage_media_id', 'updated_at', 'uuid', 'width'],
        'relations' => ['actorUser' => ['User', 'actor_user_id'], 'brand' => ['Brand', 'brand_id'], 'mediaAsset' => ['MediaAsset', 'media_asset_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'DemoBooking' => [
        'table' => 'demo_bookings',
        'columns' => ['booking_source', 'brand_id', 'created_at', 'ends_at', 'external_booking_id', 'id', 'lead_contact_id', 'lead_id', 'organisation_id', 'starts_at', 'status', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'lead' => ['Lead', 'lead_id'], 'leadContact' => ['LeadContact', 'lead_contact_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'IdempotencyRecord' => [
        'table' => 'idempotency_records',
        'columns' => ['client_key', 'created_at', 'expires_at', 'id', 'idempotency_key', 'operation', 'organisation_id', 'request_hash', 'response_body', 'response_status', 'state', 'updated_at', 'uuid'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id']],
    ],
    'IntegrationSyncCursor' => [
        'table' => 'integration_sync_cursors',
        'columns' => ['brand_id', 'created_at', 'cursor_ref', 'id', 'last_error_code', 'last_success_at', 'organisation_id', 'social_account_id', 'state', 'sync_kind', 'updated_at', 'uuid', 'watermark_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'socialAccount' => ['SocialAccount', 'social_account_id']],
    ],
    'Lead' => [
        'table' => 'leads',
        'columns' => ['brand_id', 'campaign_id', 'city', 'country_id', 'created_at', 'id', 'industry', 'notes', 'organisation_id', 'organisation_name', 'owner_user_id', 'source_kind', 'source_ref', 'status', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'campaign' => ['Campaign', 'campaign_id'], 'country' => ['Country', 'country_id'], 'organisation' => ['Organisation', 'organisation_id'], 'ownerUser' => ['User', 'owner_user_id']],
    ],
    'LeadActivity' => [
        'table' => 'lead_activities',
        'columns' => ['activity_type', 'actor_user_id', 'brand_id', 'created_at', 'id', 'lead_contact_id', 'lead_id', 'metadata', 'occurred_at', 'organisation_id', 'summary', 'updated_at'],
        'relations' => ['actorUser' => ['User', 'actor_user_id'], 'brand' => ['Brand', 'brand_id'], 'lead' => ['Lead', 'lead_id'], 'leadContact' => ['LeadContact', 'lead_contact_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'LeadContact' => [
        'table' => 'lead_contacts',
        'columns' => ['brand_id', 'contact_kind', 'created_at', 'email', 'email_lower', 'full_name', 'id', 'job_title', 'lead_id', 'organisation_id', 'phone_e164', 'preferred_channel', 'status', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'lead' => ['Lead', 'lead_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'MediaAsset' => [
        'table' => 'media_assets',
        'columns' => ['asset_state', 'brand_id', 'byte_size', 'created_at', 'created_by_ai_run_id', 'created_by_user_id', 'duration_ms', 'file_name', 'height', 'id', 'mime_type', 'object_key', 'organisation_id', 'sha256', 'source_type', 'storage_media_id', 'updated_at', 'uuid', 'width'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'createdByUser' => ['User', 'created_by_user_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'MediaPublicationPreparation' => [
        'table' => 'media_publication_preparations',
        'columns' => ['approval_payload_hash', 'blocker_code', 'brand_id', 'created_at', 'id', 'media_asset_id', 'media_sha256', 'organisation_id', 'prepared_by_user_id', 'protocol', 'protocol_manifest', 'provider', 'publication_job_id', 'state', 'updated_at', 'uuid', 'version'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'mediaAsset' => ['MediaAsset', 'media_asset_id'], 'organisation' => ['Organisation', 'organisation_id'], 'preparedByUser' => ['User', 'prepared_by_user_id'], 'publicationJob' => ['PublicationJob', 'publication_job_id']],
    ],
    'MediaPublicationPreparationEvent' => [
        'table' => 'media_publication_preparation_events',
        'columns' => ['action', 'actor_user_id', 'brand_id', 'created_at', 'details', 'from_state', 'id', 'media_publication_preparation_id', 'organisation_id', 'to_state', 'updated_at'],
        'relations' => ['actorUser' => ['User', 'actor_user_id'], 'brand' => ['Brand', 'brand_id'], 'mediaPublicationPreparation' => ['MediaPublicationPreparation', 'media_publication_preparation_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'MediaPublicationRun' => [
        'table' => 'media_publication_runs',
        'columns' => ['armed_by_user_id', 'blocker_code', 'brand_id', 'created_at', 'id', 'lease_owner', 'lease_until', 'media_publication_preparation_id', 'next_check_at', 'next_operation', 'organisation_id', 'poll_count', 'provider', 'provider_operation_reference', 'publication_job_id', 'stage_sequence', 'state', 'updated_at', 'uuid', 'verified_post_reference', 'version'],
        'relations' => ['armedByUser' => ['User', 'armed_by_user_id'], 'brand' => ['Brand', 'brand_id'], 'mediaPublicationPreparation' => ['MediaPublicationPreparation', 'media_publication_preparation_id'], 'organisation' => ['Organisation', 'organisation_id'], 'publicationJob' => ['PublicationJob', 'publication_job_id']],
    ],
    'MediaPublicationRunEvent' => [
        'table' => 'media_publication_run_events',
        'columns' => ['action', 'actor_user_id', 'brand_id', 'created_at', 'details', 'from_state', 'id', 'media_publication_run_id', 'organisation_id', 'to_state', 'updated_at'],
        'relations' => ['actorUser' => ['User', 'actor_user_id'], 'brand' => ['Brand', 'brand_id'], 'mediaPublicationRun' => ['MediaPublicationRun', 'media_publication_run_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'MediaPublicationStageAttempt' => [
        'table' => 'media_publication_stage_attempts',
        'columns' => ['brand_id', 'created_at', 'finished_at', 'id', 'media_publication_run_id', 'operation', 'organisation_id', 'outcome_code', 'stage_sequence', 'state', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'mediaPublicationRun' => ['MediaPublicationRun', 'media_publication_run_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'MediaTikTokConsent' => [
        'table' => 'media_tiktok_consents',
        'columns' => ['approval_payload_hash', 'brand_id', 'consenting_user_id', 'created_at', 'grant_fingerprint', 'id', 'media_publication_run_id', 'media_sha256', 'organisation_id', 'privacy_selection', 'updated_at', 'verified_at', 'verified_creator_options'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'consentingUser' => ['User', 'consenting_user_id'], 'mediaPublicationRun' => ['MediaPublicationRun', 'media_publication_run_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'MediaVariant' => [
        'table' => 'media_variants',
        'columns' => ['brand_id', 'byte_size', 'created_at', 'duration_ms', 'error_code', 'height', 'id', 'media_asset_id', 'mime_type', 'object_key', 'organisation_id', 'state', 'updated_at', 'uuid', 'variant_key', 'width'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'mediaAsset' => ['MediaAsset', 'media_asset_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'MetaDataDeletionRequest' => [
        'table' => 'meta_data_deletion_requests',
        'columns' => ['accepted_at', 'actor_ciphertext', 'actor_digest', 'attempts', 'completed_at', 'confirmation_code', 'created_at', 'id', 'last_error_code', 'matched_grants', 'processed_grants', 'signed_request_digest', 'state', 'updated_at', 'uuid'],
        'relations' => [],
    ],
    'MetaDataDeletionReviewDecision' => [
        'table' => 'meta_data_deletion_review_decisions',
        'columns' => ['created_at', 'disposition', 'evidence_reference', 'explanation', 'id', 'legal_basis', 'organisation_id', 'review_again_at', 'reviewed_by_user_id', 'target_id'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id'], 'reviewedByUser' => ['User', 'reviewed_by_user_id']],
    ],
    'MetaDataDeletionTarget' => [
        'table' => 'meta_data_deletion_targets',
        'columns' => ['created_at', 'id', 'organisation_id', 'processed_at', 'provider_connection_id', 'request_id', 'review_reason', 'state', 'updated_at'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id'], 'providerConnection' => ['ProviderConnection', 'provider_connection_id'], 'request' => ['MetaDataDeletionRequest', 'request_id']],
    ],
    'Notification' => [
        'table' => 'notifications',
        'columns' => ['body', 'created_at', 'email_provider_message_id', 'id', 'kind', 'organisation_id', 'read_at', 'recipient_user_id', 'related_ref', 'sent_at', 'state', 'subject', 'updated_at', 'uuid'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id'], 'recipientUser' => ['User', 'recipient_user_id']],
    ],
    'NotificationPreference' => [
        'table' => 'notification_preferences',
        'columns' => ['channel', 'created_at', 'enabled', 'id', 'notification_kind', 'organisation_id', 'updated_at', 'user_id'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id'], 'user' => ['User', 'user_id']],
    ],
    'Organisation' => [
        'table' => 'organisations',
        'columns' => ['created_at', 'created_by_user_id', 'id', 'name', 'status', 'timezone', 'updated_at', 'uuid'],
        'relations' => ['createdByUser' => ['User', 'created_by_user_id']],
    ],
    'OrganisationInvitation' => [
        'table' => 'organisation_invitations',
        'columns' => ['accepted_at', 'created_at', 'email', 'expires_at', 'id', 'invited_by_user_id', 'organisation_id', 'pending_marker', 'revoked_at', 'role_id', 'token_hash', 'updated_at', 'uuid'],
        'relations' => ['invitedByUser' => ['User', 'invited_by_user_id']],
    ],
    'OrganisationMembership' => [
        'table' => 'organisation_memberships',
        'columns' => ['created_at', 'invited_by_user_id', 'joined_at', 'organisation_id', 'role_id', 'state', 'updated_at', 'user_id'],
        'relations' => ['invitedByUser' => ['User', 'invited_by_user_id']],
    ],
    'OrganisationPreference' => [
        'table' => 'organisation_preferences',
        'columns' => ['created_at', 'date_display_format', 'default_locale', 'id', 'organisation_id', 'reporting_currency', 'time_display_format', 'updated_at', 'uuid', 'week_starts_on'],
        'relations' => [],
    ],
    'OrganisationPreferenceRule' => [
        'table' => 'organisation_preference_rules',
        'columns' => ['channel', 'created_at', 'enabled', 'event_type', 'id', 'organisation_id', 'role_id', 'updated_at', 'uuid'],
        'relations' => [],
    ],
    'OutboxEvent' => [
        'table' => 'outbox_events',
        'columns' => ['aggregate_id', 'aggregate_type', 'attempts', 'created_at', 'event_key', 'event_type', 'id', 'lease_owner', 'lease_until', 'next_retry_at', 'organisation_id', 'payload', 'pending_marker', 'processed_at', 'state', 'updated_at', 'uuid'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id']],
    ],
    'OutreachDispatch' => [
        'table' => 'outreach_dispatches',
        'columns' => ['brand_id', 'created_at', 'dispatch_key', 'error_code', 'id', 'organisation_id', 'outreach_enrollment_id', 'outreach_step_id', 'provider_message_id', 'scheduled_for', 'sent_at', 'state', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'outreachEnrollment' => ['OutreachEnrollment', 'outreach_enrollment_id'], 'outreachStep' => ['OutreachStep', 'outreach_step_id']],
    ],
    'OutreachEnrollment' => [
        'table' => 'outreach_enrollments',
        'columns' => ['active_marker', 'brand_id', 'created_at', 'enrolled_at', 'id', 'lead_contact_id', 'next_run_at', 'next_step_number', 'organisation_id', 'outreach_sequence_id', 'state', 'stop_reason', 'stopped_at', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'leadContact' => ['LeadContact', 'lead_contact_id'], 'organisation' => ['Organisation', 'organisation_id'], 'outreachSequence' => ['OutreachSequence', 'outreach_sequence_id']],
    ],
    'OutreachSequence' => [
        'table' => 'outreach_sequences',
        'columns' => ['brand_id', 'created_at', 'id', 'max_messages', 'name', 'organisation_id', 'purpose', 'status', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'OutreachStep' => [
        'table' => 'outreach_steps',
        'columns' => ['body_template', 'brand_id', 'channel', 'created_at', 'delay_minutes', 'id', 'organisation_id', 'outreach_sequence_id', 'requires_approval', 'step_number', 'subject_template', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'outreachSequence' => ['OutreachSequence', 'outreach_sequence_id']],
    ],
    'PlannerAgentActionReceipt' => [
        'table' => 'planner_agent_action_receipts',
        'columns' => ['action', 'brand_id', 'created_at', 'delegation_id', 'id', 'idempotency_key', 'input_hash', 'organisation_id', 'result'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'delegation' => ['PlannerAgentDelegation', 'delegation_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'PlannerAgentAutonomousEvent' => [
        'table' => 'planner_agent_autonomous_events',
        'columns' => ['created_at', 'event', 'evidence', 'id', 'run_id'],
        'relations' => ['run' => ['PlannerAgentAutonomousRun', 'run_id']],
    ],
    'PlannerAgentAutonomousRun' => [
        'table' => 'planner_agent_autonomous_runs',
        'columns' => ['brand_id', 'content_uuid', 'created_at', 'delegation_id', 'failure_code', 'findings', 'id', 'idempotency_key', 'lease_expires_at', 'lease_token', 'objective', 'organisation_id', 'request_hash', 'requested_by_user_id', 'research_attempts', 'research_hash', 'resource_attempts', 'resource_hash', 'resource_kind', 'resource_model', 'resource_revision_count', 'resource_text', 'sources', 'status', 'title', 'updated_at', 'uuid', 'work_order_uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'requestedByUser' => ['User', 'requested_by_user_id']],
    ],
    'PlannerAgentDelegation' => [
        'table' => 'planner_agent_delegations',
        'columns' => ['account_uuids', 'brand_id', 'created_at', 'daily_action_limit', 'daily_arm_limit', 'expires_at', 'id', 'mode', 'name', 'organisation_id', 'revoked_at', 'sanctum_token_id', 'updated_at', 'user_id'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'user' => ['User', 'user_id']],
    ],
    'PlannerAgentWorkEvent' => [
        'table' => 'planner_agent_work_events',
        'columns' => ['action', 'created_at', 'evidence', 'id', 'work_order_id'],
        'relations' => ['workOrder' => ['PlannerAgentWorkOrder', 'work_order_id']],
    ],
    'PlannerAgentWorkOrder' => [
        'table' => 'planner_agent_work_orders',
        'columns' => ['brand_id', 'created_at', 'created_by_user_id', 'delegation_id', 'draft_content_uuid', 'findings', 'id', 'objective', 'organisation_id', 'research_hash', 'sources', 'status', 'title', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'createdByUser' => ['User', 'created_by_user_id'], 'delegation' => ['PlannerAgentDelegation', 'delegation_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'PlannerAlertMailDelivery' => [
        'table' => 'planner_alert_mail_outbox',
        'columns' => ['alert_event_id', 'brand_id', 'created_at', 'id', 'lease_expires_at', 'lease_owner', 'organisation_id', 'recipient_user_id', 'sent_at', 'state', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'recipientUser' => ['User', 'recipient_user_id']],
    ],
    'PlannerIntegrationGrant' => [
        'table' => 'planner_integration_grants',
        'columns' => ['brand_id', 'created_at', 'expires_at', 'id', 'name', 'organisation_id', 'revoked_at', 'sanctum_token_id', 'updated_at', 'user_id'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'user' => ['User', 'user_id']],
    ],
    'PlannerOAuthLink' => [
        'table' => 'planner_oauth_links',
        'columns' => ['brand_id', 'created_at', 'delegation_id', 'id', 'oauth_client_id', 'organisation_id', 'revoked_at', 'updated_at', 'user_id'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'delegation' => ['PlannerAgentDelegation', 'delegation_id'], 'organisation' => ['Organisation', 'organisation_id'], 'user' => ['User', 'user_id']],
    ],
    'PlannerOAuthUser' => [
        'table' => 'users',
        'columns' => ['created_at', 'email', 'email_verified_at', 'id', 'name', 'password', 'remember_token', 'updated_at'],
        'relations' => [],
    ],
    'PlannerOperationalAlert' => [
        'table' => 'planner_operational_alerts',
        'columns' => ['brand_id', 'created_at', 'first_seen_at', 'id', 'last_seen_at', 'observed_count', 'organisation_id', 'resolved_at', 'signal', 'state', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'PlannerOperationalAlertEvent' => [
        'table' => 'planner_operational_alert_events',
        'columns' => ['brand_id', 'created_at', 'event_type', 'id', 'observed_at', 'observed_count', 'organisation_id', 'planner_operational_alert_id', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'plannerOperationalAlert' => ['PlannerOperationalAlert', 'planner_operational_alert_id']],
    ],
    'PrivacyRetentionFollowup' => [
        'table' => 'privacy_retention_followups',
        'columns' => ['acknowledged_at', 'acknowledged_by_user_id', 'acknowledgement_notes', 'created_at', 'due_at', 'id', 'organisation_id', 'review_decision_id', 'status', 'updated_at'],
        'relations' => ['acknowledgedByUser' => ['User', 'acknowledged_by_user_id'], 'organisation' => ['Organisation', 'organisation_id'], 'reviewDecision' => ['MetaDataDeletionReviewDecision', 'review_decision_id']],
    ],
    'ProviderConnection' => [
        'table' => 'provider_connections',
        'columns' => ['access_token_secret_ref', 'created_at', 'data_access_expires_at', 'external_actor_id', 'id', 'inspection_attempts', 'inspection_lease_token', 'inspection_lease_until', 'inspection_retry_at', 'last_inspection_result', 'last_refresh_at', 'last_refresh_result', 'last_verified_at', 'organisation_id', 'provider', 'reconnect_reason', 'refresh_attempts', 'refresh_lease_token', 'refresh_lease_until', 'refresh_retry_at', 'refresh_token_secret_ref', 'scopes', 'status', 'token_expires_at', 'updated_at', 'uuid'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id']],
    ],
    'ProviderConnectionCredential' => [
        'table' => 'provider_connection_credentials',
        'columns' => ['access_token_ciphertext', 'created_at', 'id', 'organisation_id', 'provider_connection_id', 'refresh_token_ciphertext', 'refresh_token_expires_at', 'updated_at'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id'], 'providerConnection' => ['ProviderConnection', 'provider_connection_id']],
    ],
    'ProviderGrantGroup' => [
        'table' => 'provider_grant_groups',
        'columns' => ['actor_digest', 'application_id', 'created_at', 'id', 'provider_family', 'updated_at'],
        'relations' => [],
    ],
    'ProviderGrantLink' => [
        'table' => 'provider_grant_links',
        'columns' => ['created_at', 'detached_at', 'grant_group_id', 'id', 'organisation_id', 'provider_connection_id', 'updated_at'],
        'relations' => ['grantGroup' => ['ProviderGrantGroup', 'grant_group_id'], 'organisation' => ['Organisation', 'organisation_id'], 'providerConnection' => ['ProviderConnection', 'provider_connection_id']],
    ],
    'ProviderGrantReconciliationDecision' => [
        'table' => 'provider_grant_reconciliation_decisions',
        'columns' => ['created_at', 'evidence_digest', 'evidence_id', 'id', 'organisation_id', 'outcome', 'rationale', 'reviewed_by_user_id', 'revocation_request_id', 'uuid'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id'], 'reviewedByUser' => ['User', 'reviewed_by_user_id']],
    ],
    'ProviderGrantReconciliationEvidence' => [
        'table' => 'provider_grant_reconciliation_evidence',
        'columns' => ['created_at', 'evidence_digest', 'id', 'organisation_id', 'reference', 'revocation_request_id', 'source_type', 'submitted_by_user_id', 'summary', 'uuid'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id'], 'submittedByUser' => ['User', 'submitted_by_user_id']],
    ],
    'ProviderGrantRevocationRequest' => [
        'table' => 'provider_grant_revocation_requests',
        'columns' => ['affected_connections', 'attempted_at', 'confirmed_at', 'confirmed_by_user_id', 'created_at', 'expires_at', 'grant_group_id', 'id', 'impact_digest', 'lease_token', 'lease_until', 'organisation_id', 'provider', 'provider_acknowledged_at', 'provider_connection_id', 'reason', 'requested_by_user_id', 'safe_error_code', 'status', 'updated_at', 'uuid'],
        'relations' => ['confirmedByUser' => ['User', 'confirmed_by_user_id'], 'grantGroup' => ['ProviderGrantGroup', 'grant_group_id'], 'organisation' => ['Organisation', 'organisation_id'], 'providerConnection' => ['ProviderConnection', 'provider_connection_id'], 'requestedByUser' => ['User', 'requested_by_user_id']],
    ],
    'ProviderMetricObservation' => [
        'table' => 'provider_metric_observations',
        'columns' => ['brand_id', 'channel_reference', 'comments', 'created_at', 'external_post_id', 'id', 'likes', 'observed_at', 'organisation_id', 'provenance', 'provider', 'provider_metric_sync_run_id', 'published_post_id', 'response_sha256', 'updated_at', 'views'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'providerMetricSyncRun' => ['ProviderMetricSyncRun', 'provider_metric_sync_run_id'], 'publishedPost' => ['PublishedPost', 'published_post_id']],
    ],
    'ProviderMetricSyncPolicy' => [
        'table' => 'provider_metric_sync_policies',
        'columns' => ['brand_id', 'configured_by_user_id', 'created_at', 'enabled', 'id', 'last_queued_day', 'max_posts_per_day', 'organisation_id', 'updated_at', 'utc_hour', 'version'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'configuredByUser' => ['User', 'configured_by_user_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'ProviderMetricSyncRun' => [
        'table' => 'provider_metric_sync_runs',
        'columns' => ['attempts', 'brand_id', 'completed_at', 'created_at', 'id', 'lease_owner', 'lease_until', 'next_attempt_at', 'organisation_id', 'outcome_code', 'provider', 'published_post_id', 'requested_by_user_id', 'state', 'sync_day', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'publishedPost' => ['PublishedPost', 'published_post_id'], 'requestedByUser' => ['User', 'requested_by_user_id']],
    ],
    'PublicationAttempt' => [
        'table' => 'publication_attempts',
        'columns' => ['attempt_number', 'brand_id', 'created_at', 'error_code', 'finished_at', 'http_status', 'id', 'operation_key', 'organisation_id', 'provider_request_id', 'provider_upload_id', 'publication_job_id', 'response_redacted', 'result', 'started_at', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'publicationJob' => ['PublicationJob', 'publication_job_id']],
    ],
    'PublicationEvent' => [
        'table' => 'publication_events',
        'columns' => ['actor_ref', 'actor_type', 'brand_id', 'created_at', 'details', 'event_type', 'from_state', 'id', 'occurred_at', 'organisation_id', 'publication_job_id', 'to_state', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'publicationJob' => ['PublicationJob', 'publication_job_id']],
    ],
    'PublicationJob' => [
        'table' => 'publication_jobs',
        'columns' => ['approval_request_id', 'armed_at', 'armed_by_user_id', 'attempt_count', 'brand_id', 'content_target_id', 'created_at', 'due_state_marker', 'id', 'last_error_code', 'last_error_message', 'lease_owner', 'lease_state_marker', 'lease_until', 'max_attempts', 'next_retry_at', 'occurrence_key', 'organisation_id', 'run_at', 'state', 'updated_at', 'version'],
        'relations' => ['approvalRequest' => ['ApprovalRequest', 'approval_request_id'], 'armedByUser' => ['User', 'armed_by_user_id'], 'brand' => ['Brand', 'brand_id'], 'contentTarget' => ['ContentTarget', 'content_target_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'PublicationReconciliationDecision' => [
        'table' => 'publication_reconciliation_decisions',
        'columns' => ['brand_id', 'created_at', 'disposition', 'evidence_id', 'id', 'organisation_id', 'publication_job_id', 'rationale', 'reviewed_by', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'publicationJob' => ['PublicationJob', 'publication_job_id']],
    ],
    'PublicationReconciliationEvidence' => [
        'table' => 'publication_reconciliation_evidence',
        'columns' => ['brand_id', 'created_at', 'evidence_kind', 'evidence_sha256', 'external_reference', 'id', 'organisation_id', 'publication_job_id', 'submitted_by', 'summary', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'publicationJob' => ['PublicationJob', 'publication_job_id']],
    ],
    'PublicationSandboxEvent' => [
        'table' => 'publication_sandbox_events',
        'columns' => ['created_at', 'event', 'id', 'publication_sandbox_run_id', 'safe_details'],
        'relations' => ['publicationSandboxRun' => ['PublicationSandboxRun', 'publication_sandbox_run_id']],
    ],
    'PublicationSandboxRun' => [
        'table' => 'publication_sandbox_runs',
        'columns' => ['attempt_count', 'brand_id', 'created_at', 'id', 'lease_token', 'lease_until', 'operation_key', 'organisation_id', 'outcome_code', 'outcome_kind', 'provider', 'publication_job_id', 'safe_result', 'scenario', 'status', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'publicationJob' => ['PublicationJob', 'publication_job_id']],
    ],
    'PublishedPost' => [
        'table' => 'published_posts',
        'columns' => ['brand_id', 'created_at', 'external_post_id', 'external_url', 'id', 'last_checked_at', 'organisation_id', 'provider', 'publication_job_id', 'published_at', 'remote_status', 'social_account_id', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'publicationJob' => ['PublicationJob', 'publication_job_id'], 'socialAccount' => ['SocialAccount', 'social_account_id']],
    ],
    'PublishingWindow' => [
        'table' => 'publishing_windows',
        'columns' => ['brand_id', 'created_at', 'enabled', 'id', 'local_end', 'local_start', 'organisation_id', 'social_account_id', 'timezone', 'updated_at', 'weekday'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'socialAccount' => ['SocialAccount', 'social_account_id']],
    ],
    'RecurringCampaignOccurrence' => [
        'table' => 'recurring_campaign_occurrences',
        'columns' => ['brand_id', 'created_at', 'held_at', 'id', 'local_at', 'occurrence_key', 'organisation_id', 'recurring_schedule_id', 'run_at', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'recurringSchedule' => ['RecurringSchedule', 'recurring_schedule_id']],
    ],
    'RecurringSchedule' => [
        'table' => 'recurring_schedules',
        'columns' => ['brand_id', 'content_target_id', 'created_at', 'created_by_user_id', 'end_at', 'id', 'max_occurrences', 'next_expand_at', 'organisation_id', 'rrule', 'start_local', 'state', 'timezone', 'updated_at', 'version'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'contentTarget' => ['ContentTarget', 'content_target_id'], 'createdByUser' => ['User', 'created_by_user_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'ReportJob' => [
        'table' => 'report_jobs',
        'columns' => ['brand_id', 'campaign_id', 'created_at', 'error_code', 'finished_at', 'format', 'id', 'organisation_id', 'output_object_key', 'period_end', 'period_start', 'report_type', 'requested_by_user_id', 'started_at', 'state', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'campaign' => ['Campaign', 'campaign_id'], 'organisation' => ['Organisation', 'organisation_id'], 'requestedByUser' => ['User', 'requested_by_user_id']],
    ],
    'RetentionRequest' => [
        'table' => 'retention_requests',
        'columns' => ['completed_at', 'created_at', 'due_at', 'id', 'organisation_id', 'request_kind', 'requested_at', 'requested_by_user_id', 'resolution_note', 'state', 'subject_kind', 'subject_ref', 'updated_at', 'uuid'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id'], 'requestedByUser' => ['User', 'requested_by_user_id']],
    ],
    'SocialAccount' => [
        'table' => 'social_accounts',
        'columns' => ['account_kind', 'brand_id', 'created_at', 'display_name', 'external_account_id', 'handle', 'id', 'last_sync_at', 'organisation_id', 'provider', 'provider_connection_id', 'status', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'providerConnection' => ['ProviderConnection', 'provider_connection_id']],
    ],
    'SocialAccountCapability' => [
        'table' => 'social_account_capabilities',
        'columns' => ['brand_id', 'capability', 'checked_at', 'created_at', 'id', 'mode', 'organisation_id', 'reason', 'social_account_id', 'updated_at', 'uuid', 'valid_until'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'socialAccount' => ['SocialAccount', 'social_account_id']],
    ],
    'SocialAccountStatusEvent' => [
        'table' => 'social_account_status_events',
        'columns' => ['brand_id', 'created_at', 'details', 'event_type', 'id', 'new_status', 'old_status', 'organisation_id', 'recorded_at', 'social_account_id', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'socialAccount' => ['SocialAccount', 'social_account_id']],
    ],
    'SocialMetricSnapshot' => [
        'table' => 'social_metric_snapshots',
        'columns' => ['account_reference', 'brand_id', 'clicks', 'comments', 'created_at', 'external_post_reference', 'id', 'impressions', 'likes', 'observed_at', 'organisation_id', 'payload_hash', 'provider', 'reach', 'shares', 'source_reference', 'source_type', 'submission_key', 'submitted_by_user_id', 'updated_at'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'submittedByUser' => ['User', 'submitted_by_user_id']],
    ],
    'SocialOAuthSelection' => [
        'table' => 'social_oauth_selections',
        'columns' => ['brand_id', 'candidates_ciphertext', 'consumed_at', 'created_at', 'expires_at', 'grant_ciphertext', 'id', 'meta_actor_digest', 'organisation_id', 'provider', 'updated_at', 'user_id', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'user' => ['User', 'user_id']],
    ],
    'SocialOauthAttempt' => [
        'table' => 'social_oauth_attempts',
        'columns' => ['brand_id', 'consumed_at', 'created_at', 'expires_at', 'id', 'organisation_id', 'provider', 'redirect_uri', 'state_digest', 'updated_at', 'user_id', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id'], 'user' => ['User', 'user_id']],
    ],
    'State' => [
        'table' => 'states',
        'columns' => ['country_code', 'country_id', 'country_name', 'created_at', 'deleted_at', 'id', 'latitude', 'longitude', 'name', 'state_code', 'type', 'updated_at'],
        'relations' => [],
    ],
    'SuppressionEntry' => [
        'table' => 'suppression_entries',
        'columns' => ['brand_id', 'channel', 'created_at', 'effective_at', 'expires_at', 'id', 'identifier_hash', 'organisation_id', 'reason', 'updated_at', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'TrackingLink' => [
        'table' => 'tracking_links',
        'columns' => ['brand_id', 'campaign_id', 'created_at', 'destination_url', 'id', 'organisation_id', 'short_code', 'status', 'updated_at', 'utm_campaign', 'utm_content', 'utm_medium', 'utm_source', 'uuid'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'campaign' => ['Campaign', 'campaign_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'Unipotence' => [
        'table' => 'unipotences',
        'columns' => ['claim_token', 'completed_at', 'created_at', 'id', 'indeterminate_at', 'key', 'locked_until', 'reference', 'request_hash', 'response_body', 'response_headers', 'response_status', 'scope', 'state', 'updated_at'],
        'relations' => [],
    ],
    'UsageLedger' => [
        'table' => 'usage_ledgers',
        'columns' => ['created_at', 'id', 'organisation_id', 'quantity', 'recorded_at', 'source_ref', 'unit', 'updated_at', 'usage_kind', 'uuid'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id']],
    ],
    'User' => [
        'table' => 'users',
        'columns' => ['created_at', 'email', 'email_verified_at', 'id', 'name', 'password', 'remember_token', 'updated_at'],
        'relations' => [],
    ],
    'WebhookEvent' => [
        'table' => 'webhook_events',
        'columns' => ['attempts', 'created_at', 'error_code', 'external_event_id', 'id', 'next_retry_at', 'organisation_id', 'provider', 'received_at', 'redacted_payload', 'social_account_id', 'state', 'updated_at', 'uuid', 'verified'],
        'relations' => ['organisation' => ['Organisation', 'organisation_id'], 'socialAccount' => ['SocialAccount', 'social_account_id']],
    ],
    'YouTubeResumableAttempt' => [
        'table' => 'youtube_resumable_attempts',
        'columns' => ['action', 'brand_id', 'created_at', 'finished_at', 'id', 'offset_bytes', 'organisation_id', 'outcome_code', 'sequence', 'state', 'updated_at', 'youtube_resumable_transfer_id'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'YouTubeResumableEvent' => [
        'table' => 'youtube_resumable_events',
        'columns' => ['action', 'brand_id', 'created_at', 'from_state', 'id', 'organisation_id', 'safe_details', 'to_state', 'updated_at', 'youtube_resumable_transfer_id'],
        'relations' => ['brand' => ['Brand', 'brand_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
    'YouTubeResumableTransfer' => [
        'table' => 'youtube_resumable_transfers',
        'columns' => ['armed_by_user_id', 'blocker_code', 'brand_id', 'canonical_mime', 'canonical_sha256', 'committed_bytes', 'created_at', 'id', 'lease_owner', 'lease_until', 'media_asset_id', 'media_publication_run_id', 'next_action', 'next_check_at', 'organisation_id', 'poll_count', 'sequence', 'session_url_ciphertext', 'state', 'total_bytes', 'updated_at', 'uuid', 'version', 'video_id_ciphertext'],
        'relations' => ['armedByUser' => ['User', 'armed_by_user_id'], 'brand' => ['Brand', 'brand_id'], 'mediaAsset' => ['MediaAsset', 'media_asset_id'], 'mediaPublicationRun' => ['MediaPublicationRun', 'media_publication_run_id'], 'organisation' => ['Organisation', 'organisation_id']],
    ],
];
