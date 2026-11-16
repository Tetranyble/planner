<?php

namespace App\Services\Integrations;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Analytics\BrandOperationsDashboard;
use App\Services\Analytics\ProviderPerformanceDashboard;
use App\Services\Content\PublicationCalendar;
use App\Services\Publishing\PublicationOperations;
use Illuminate\Support\Facades\Validator;

/** Calls the SAME authorized application services as the REST/UTCP read boundary. */
final class PlannerMcpReader
{
    public function __construct(
        private readonly BrandOperationsDashboard $operations,
        private readonly PublicationCalendar $calendar,
        private readonly PlannerOperationalSafety $safety,
        private readonly ProviderPerformanceDashboard $performance,
        private readonly PublicationOperations $publication,
    ) {}

    /** @param array<string, mixed> $input */
    public function read(string $tool, User $actor, Organisation $org, Brand $brand, array $input = []): array
    {
        // No generic tool dispatch or user-submitted account/scope identifiers.
        if (in_array($tool, ['brand_operations', 'publication_calendar'], true)) {
            $dates = Validator::make($input, [
                'from' => ['required', 'date_format:Y-m-d'],
                'to' => ['required', 'date_format:Y-m-d'],
            ])->validate();
        }

        return match ($tool) {
            'brand_operations' => $this->operations->get($actor, $org, $brand, $dates['from'], $dates['to']),
            'publication_calendar' => $this->calendar->list($actor, $org, $brand, $dates['from'], $dates['to']),
            'operational_safety' => $this->safety->show($actor, $org, $brand),
            'provider_performance' => $this->performance->show($actor, $org, $brand),
            'publication_history' => $this->publication->overview($actor, $org, $brand),
            default => throw new \InvalidArgumentException('Unknown read-only tool.'),
        };
    }
}
