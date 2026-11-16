<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerAlertMailDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlannerAlertMailDelivery> */
final class PlannerAlertMailDeliveryFactory extends Factory
{
    protected $model = PlannerAlertMailDelivery::class;

    public function definition(): array
    {
        return ['organisation_id' => Organisation::factory(), 'brand_id' => Brand::factory(),
            'recipient_user_id' => User::factory(), 'alert_event_id' => null, 'state' => 'PENDING'];
    }

    public function forEvidence(int $orgId, int $brandId, int $eventId, int $userId): static
    {
        return $this->state(fn () => ['organisation_id' => $orgId, 'brand_id' => $brandId,
            'alert_event_id' => $eventId, 'recipient_user_id' => $userId]);
    }
}
