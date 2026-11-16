<?php

namespace App\Services\Integrations;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class PlannerUtcpManual
{
    public function __construct(private readonly BrandPermissionGuard $guard) {}

    public function build(User $actor, Organisation $org, Brand $brand): array
    {
        if ((int) $brand->organisation_id !== (int) $org->id ||
            ! $this->guard->allows($actor, $brand, 'approval_request.index')) {
            throw new AuthorizationException('Integration discovery denied.');
        }
        $base = rtrim((string) config('app.url'), '/');
        if (! str_starts_with($base, 'https://') && ! app()->environment('local', 'testing')) {
            throw new \RuntimeException('HTTPS APP_URL required for UTCP.');
        }
        $root = $base.'/api/v1/integrations/organisations/'.$org->uuid.'/brands/'.$brand->uuid;
        $auth = ['auth_type' => 'api_key', 'api_key' => 'Bearer ${PLANNER_API_TOKEN}',
            'var_name' => 'Authorization', 'location' => 'header'];
        $dateSchema = ['type' => 'object', 'properties' => [
            'from' => ['type' => 'string', 'format' => 'date'], 'to' => ['type' => 'string', 'format' => 'date']],
            'required' => ['from', 'to'], 'additionalProperties' => false];
        $tool = static function (string $name, string $description, string $path, array $inputs) use ($root, $auth): array {
            return ['name' => $name, 'description' => $description, 'inputs' => $inputs,
                'outputs' => ['type' => 'object'],
                'tool_call_template' => ['call_template_type' => 'http',
                    'url' => $root.$path, 'http_method' => 'GET', 'auth' => $auth]];
        };

        return ['utcp_version' => '1.1.0', 'manual_version' => '1.0.0',
            'info' => ['title' => 'Planner read-only brand operations', 'version' => '1.0.0',
                'description' => 'Brand-bound, explicit read-only analytics and publication visibility. No posting or state changes.'],
            'tools' => [
                $tool('planner_brand_operations', 'Internal workflow counts for a UTC date range.',
                    '/operations', $dateSchema),
                $tool('planner_publication_calendar', 'Publication plans in a UTC range (not delivery permission).',
                    '/calendar', $dateSchema),
                $tool('planner_operational_safety', 'Read-only counts of uncertain, failed or overdue operations.',
                    '/safety', ['type' => 'object', 'properties' => new \stdClass]),
                $tool('planner_provider_performance', 'Provider-specific matched metrics; no combined ROI.',
                    '/provider-performance', ['type' => 'object', 'properties' => new \stdClass]),
                $tool('planner_publication_history', 'Read-only publication queue and attempts.',
                    '/publication-operations', ['type' => 'object', 'properties' => new \stdClass]),
            ],
            'capabilities' => ['read_only' => true, 'writes_available' => false, 'publishing_available' => false]];
    }
}
