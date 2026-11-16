<?php

use App\Authorization\CurrentOrganisationKinshipResolver;
use App\Models\Organisation;
use Tetranyble\Kinship\Catalog\ConfigPermissionCatalog;
use Tetranyble\Kinship\Http\Middleware\PermissionMiddleware;
use Tetranyble\Kinship\Http\Middleware\RoleMiddleware;
use Tetranyble\Kinship\Http\Middleware\ValidateImpersonationSession;
use Tetranyble\Kinship\Http\Middleware\ValidateStatelessImpersonation;
use Tetranyble\Kinship\Impersonation\GateImpersonationAuthorizer;
use Tetranyble\Kinship\Models\Group;
use Tetranyble\Kinship\Models\Permission;
use Tetranyble\Kinship\Models\Role;
use Tetranyble\Kinship\Permissions\DatabasePermissionGrantSource;
use Tetranyble\Kinship\Workspace\ModelAuthorizationContextResolver;

return [
    'models' => [
        'role' => Role::class,
        'permission' => Permission::class,
        'group' => Group::class,
        // Host-owned tenant model. It must implement Contracts\Workspace;
        // Concerns\IsWorkspace provides the conventional implementation.
        'workspace' => Organisation::class,
        'user' => null,
    ],

    'tables' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'role_user' => 'role_user',
        'permission_user' => 'permission_user',
        'permission_role' => 'permission_role',
        'groups' => 'groups',
        'group_user' => 'group_user',
        'group_role' => 'group_role',
        'group_permission' => 'group_permission',
    ],

    'columns' => [
        'role_foreign_key' => 'role_id',
        'permission_foreign_key' => 'permission_id',
        'user_foreign_key' => 'user_id',
        'group_foreign_key' => 'group_id',
    ],

    'workspace' => [
        // null auto-detects the WorkspaceSubject contract or a mapping override.
        // Set false to force single-workspace mode or true for a custom resolver.
        'enabled' => true,
        'resolver' => CurrentOrganisationKinshipResolver::class,
        'context_resolver' => ModelAuthorizationContextResolver::class,
        'subject_foreign_key' => 'workspace_id',
        'role_foreign_key' => 'workspace_id',
        'group_foreign_key' => 'workspace_id',
        'global_scope_value' => '__kinship_global__',
        'mapping' => [
            // The model comes from kinship.models.workspace. Only override
            // relationship/key names here when the host schema is non-standard.
            'relationship' => null,
            'subject_foreign_key' => null,
            'workspace_owner_key' => null,
            'role_foreign_key' => null,
            'group_foreign_key' => null,
        ],
    ],

    'group' => [
        // Scalar group lookup is optional convenience. Model instances always work.
        // Existing applications may map this to ['code'], ['slug'], etc.
        'lookup_columns' => ['name', 'label'],
    ],

    'acting_roles' => [
        'enabled' => true,
        // replace evaluates only the assumed role; merge adds it to normal grants.
        'mode' => 'replace',
        'allow_unassigned' => false,
        'session_prefix' => 'kinship.acting_role',
    ],

    'impersonation' => [
        // User impersonation is independent of acting/assumed roles and must
        // be deliberately enabled by the host application.
        'enabled' => false,
        'authorizer' => GateImpersonationAuthorizer::class,
        'ability' => 'kinship.impersonate',
        'require_reason' => true,
        'max_reason_length' => 1000,
        'require_same_user_type' => true,
        'ttl' => 1800,
        'session_key' => 'kinship.impersonation',
        // Create a new subclass for application-owned MFA/account checks;
        // existing application middleware does not need to be modified.
        'middleware' => ValidateImpersonationSession::class,
        // Configure an application token broker to opt into stateless support.
        'stateless_broker' => null,
        // May be replaced by a subclass for application-owned conditions.
        'stateless_middleware' => ValidateStatelessImpersonation::class,
        // Explicit route/group placement is the secure default. Set true only
        // when the host intentionally wants Kinship on an entire group.
        'auto_middleware' => false,
        'middleware_group' => 'web',
    ],

    'cache' => [
        // Uses Laravel's configured cache store when null. Switching the host
        // application to Redis requires no Kinship-specific integration.
        'enabled' => true,
        'store' => null,
        'prefix' => 'kinship',
        'ttl' => 3600,
    ],

    // Applications can append PermissionGrantSource implementations for
    // teams, groups, subscriptions, or another domain-owned grant source.
    'permission_sources' => [
        DatabasePermissionGrantSource::class,
    ],

    'catalog' => [
        // Catalog seeding never runs automatically. Enable it explicitly and
        // execute `php artisan kinship:seed` for the desired scope.
        'enabled' => true,
        'source' => ConfigPermissionCatalog::class,
        'separator' => '.',

        // Compatibility adapter for applications that derive permissions from
        // Eloquent model class names. Scanning occurs only during kinship:seed.
        'discovery' => [
            'enabled' => true,
            'path' => 'Models',
            'namespace' => null,
        ],

        'abilities' => [
            'index' => 'View All',
            'view' => 'Read',
            'store' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'restore' => 'Restore',
            'force_delete' => 'Permanently Delete',
        ],

        // Package-owned models live outside app/Models and therefore require
        // explicit catalog resources. Their permission grants stay owner-only.
        'resources' => [
            'role' => ['label' => 'Role', 'abilities' => ['index', 'view', 'store', 'update', 'delete']],
            'permission' => ['label' => 'Permission', 'abilities' => ['index', 'view']],
            'group' => ['label' => 'Group', 'abilities' => ['index', 'view', 'store', 'update', 'delete']],
        ],

        // Model CRUD permissions come from ModelResourceDiscovery, not a manually
        // maintained permission list. Each discovered model gets:
        // {snake_case_model}.{index|view|store|update|delete|restore|force_delete}.
        // Domain capabilities below cannot be inferred from an Eloquent model.
        'permissions' => [
            'content.approve' => 'Approve Content for Publication',
            'publication.schedule' => 'Schedule Approved Posts',
            'publication.cancel' => 'Cancel Queued Publications',
            'publication.retry' => 'Retry Failed Publications',
            'social-account.connect' => 'Connect Social Accounts',
            'social-account.disconnect' => 'Disconnect Social Accounts',
            'social-grant.revoke' => 'Prepare and Confirm Organisation-wide OAuth Grant Revocation',
            'social-grant.reconcile' => 'Review and Independently Reconcile Uncertain Provider Grant Revocation Outcomes',
            'integration.manage' => 'Configure API Integrations and Credentials',
            'automation.execute' => 'Execute Configured Automations',
            'ai-run.execute' => 'Start AI Marketing Runs',
            'ai-run.approve-action' => 'Approve External AI Actions',
            'lead.export' => 'Export Lead Data',
            'analytics.export' => 'Export Marketing Performance Reports',
            'claim.submit-review' => 'Submit a Draft Marketing Claim for Review',
            'claim.review' => 'Approve or Reject Marketing Claims',
            'knowledge-source.verify' => 'Verify or Reject Brand Knowledge Sources',
            'claim-evidence.verify' => 'Verify or Reject Claim Evidence',
            'approval-policy.activate' => 'Activate a Versioned Brand Approval Policy',
            'privacy-deletion.review' => 'Review and Resolve Provider Data Deletion Requests',
        ],

        // Explicit, least-privilege role grants. Avoid broad *.view and *.index
        // patterns: discovery includes credentials, audit and user data models.
        // Only the owner has full control. Brand-specific membership remains
        // an additional authorization boundary enforced by domain services.
        'roles' => [
            'viewer' => [
                'label' => 'Viewer',
                'description' => 'Read approved brands, campaigns and high-level performance',
                'order' => 40,
                'is_system' => true,
                'permissions' => [
                    'organisation.index', 'organisation.view',
                    'brand.index', 'brand.view',
                    'brand_profile.index', 'brand_profile.view',
                    'brand_audience_segment.index', 'brand_audience_segment.view',
                    'brand_voice_profile.index', 'brand_voice_profile.view',
                    'brand_claim.index', 'brand_claim.view',
                    'brand_approval_policy.index', 'brand_approval_policy.view',
                    'campaign.index', 'campaign.view',
                    'content_item.index', 'content_item.view',
                    'publication_job.index', 'publication_job.view',
                    'published_post.index', 'published_post.view',
                    'analytic_snapshot.index', 'analytic_snapshot.view',
                    'analytic_rollup.index', 'analytic_rollup.view',
                ],
            ],
            'contributor' => [
                'label' => 'Contributor',
                'description' => 'Draft and edit marketing content without publishing authority',
                'order' => 30,
                'is_system' => true,
                'permissions' => [
                    'organisation.index', 'organisation.view',
                    'brand.index', 'brand.view',
                    'brand_audience_segment.store', 'brand_voice_profile.store', 'brand_claim.store',
                    'brand_knowledge_source.store', 'brand_claim_evidence.store', 'claim.submit-review',
                    'brand_profile.index', 'brand_profile.view',
                    'brand_audience_segment.index', 'brand_audience_segment.view',
                    'brand_voice_profile.index', 'brand_voice_profile.view',
                    'brand_claim.index', 'brand_claim.view',
                    'brand_approval_policy.index', 'brand_approval_policy.view',
                    'campaign.index', 'campaign.view', 'campaign.store', 'campaign.update',
                    'content_item.index', 'content_item.view', 'content_item.store', 'content_item.update',
                    'content_version.index', 'content_version.view', 'content_version.store',
                    'media_asset.index', 'media_asset.view', 'media_asset.store',
                    'content_target.store', 'content_target.view',
                    'approval_request.index', 'approval_request.store', 'approval_request.update',
                    'publication_job.index', 'publication_job.view',
                    'published_post.index', 'published_post.view',
                    'analytic_snapshot.index', 'analytic_snapshot.view',
                    'social_account.index',
                ],
            ],
            'manager' => [
                'label' => 'Manager',
                'description' => 'Manage brands, staff access, campaigns and approved publishing; no owner-level organisation administration',
                'order' => 20,
                'is_system' => true,
                'permissions' => [
                    'organisation.index', 'organisation.view',
                    'brand.index', 'brand.view', 'brand.store', 'brand.update',
                    'brand_profile.index', 'brand_profile.view',
                    'brand_audience_segment.index', 'brand_audience_segment.view',
                    'brand_voice_profile.index', 'brand_voice_profile.view',
                    'brand_claim.index', 'brand_claim.view',
                    'brand_approval_policy.index', 'brand_approval_policy.view',
                    'brand_audience_segment.store', 'brand_voice_profile.store', 'brand_claim.store',
                    'brand_knowledge_source.store', 'brand_claim_evidence.store', 'claim.submit-review',
                    'brand_profile.update', 'brand_claim_policy.update',
                    'brand_membership.index', 'brand_membership.view',
                    'brand_membership.store', 'brand_membership.update', 'brand_membership.delete',
                    'campaign.index', 'campaign.view', 'campaign.store', 'campaign.update',
                    'content_item.index', 'content_item.view', 'content_item.store', 'content_item.update',
                    'content_version.index', 'content_version.view', 'content_version.store',
                    'media_asset.index', 'media_asset.view', 'media_asset.store',
                    'content_target.store', 'content_target.view',
                    'approval_request.index', 'approval_request.store', 'approval_request.update',
                    'social_account.index', 'social_account.view',
                    'publication_job.index', 'publication_job.view',
                    'published_post.index', 'published_post.view',
                    'analytic_snapshot.index', 'analytic_snapshot.view',
                    'analytic_rollup.index', 'analytic_rollup.view',
                    'lead.index', 'lead.view', 'lead.store', 'lead.update',
                    'automation_rule.index', 'automation_rule.view', 'automation_rule.store', 'automation_rule.update',
                    'ai_run.index', 'ai_run.view',
                    'knowledge-source.verify', 'claim-evidence.verify', 'claim.review',
                    'content.approve', 'publication.schedule', 'publication.cancel',
                    'social-account.connect', 'social-account.disconnect',
                    'automation.execute', 'ai-run.execute', 'analytics.export',
                ],
            ],
            'owner' => [
                'label' => 'Owner',
                'description' => 'Full organisation control including membership, integrations and exports',
                'order' => 10,
                'is_system' => true,
                'permissions' => ['*'],
            ],
        ],
    ],

    'middleware' => [
        'register_aliases' => true,
        'aliases' => [
            'kinship.role' => RoleMiddleware::class,
            'kinship.permission' => PermissionMiddleware::class,
            'kinship.impersonation.valid' => ValidateImpersonationSession::class,
        ],
        'unauthorized_message' => 'This action is unauthorized.',
    ],

    // Existing applications can disable this while their original migration
    // files remain in the host application's migration directory.
    'migrations' => [
        'load' => true,
    ],
];
