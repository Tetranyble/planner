<?php

namespace App\Services\Content;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Tenant-safe calendar overview; existence of a plan never implies a post will be sent. */
final class PublicationCalendar
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly PublicationIntentRepositoryContract $jobs) {}

    public function list(User $actor, Organisation $org, Brand $brand, string $from, string $to): array
    {
        if ((int) $brand->organisation_id !== (int) $org->id ||
            ! $this->guard->allows($actor, $brand, 'approval_request.index')) {
            throw new AuthorizationException('Publication calendar access denied.');
        }
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, 'UTC');
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $to, 'UTC');
        } catch (\Throwable) {
            throw ValidationException::withMessages(['dates' => 'Dates must be valid YYYY-MM-DD values.']);
        }
        if (! $start || ! $end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to ||
            $start->gt($end) || $start->diffInDays($end) > 90) {
            throw ValidationException::withMessages(['dates' => 'Choose an inclusive UTC range of at most 90 days.']);
        }
        $rows = $this->jobs->between($org, $brand, $start->startOfDay()->format('Y-m-d H:i:s'),
            $end->addDay()->startOfDay()->format('Y-m-d H:i:s'), 500);

        return ['timezone' => 'UTC', 'from' => $from, 'to' => $to, 'dispatch_enabled' => false,
            'plans' => $rows->map(static fn ($job) => [
                'id' => (int) $job->id,
                'content_target_id' => (int) $job->content_target_id,
                'approval_request_id' => (int) $job->approval_request_id,
                'run_at' => $job->run_at?->toIso8601String(),
                'state' => $job->state, 'can_dispatch' => false,
            ])->all()];
    }
}
