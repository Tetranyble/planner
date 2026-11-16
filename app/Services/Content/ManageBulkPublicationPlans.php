<?php

namespace App\Services\Content;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Atomic multi-target, operator-held planning. No remote publishing, dispatch or state promotion. */
final class ManageBulkPublicationPlans
{
    public function __construct(private readonly ManagePublicationIntents $intents) {}

    /** @param list<array{item_uuid:string,target_uuid:string,approval_request_id:int,occurrence_key:string,run_at:string}> $plans */
    public function create(User $actor, Organisation $org, Brand $brand, array $plans): array
    {
        if (count($plans) < 1 || count($plans) > 20) {
            throw ValidationException::withMessages(['plans' => 'Provide between 1 and 20 publication plans.']);
        }
        $dedup = [];
        foreach ($plans as $i => $plan) {
            $identity = $plan['target_uuid'].'|'.$plan['occurrence_key'];
            if (isset($dedup[$identity])) {
                throw ValidationException::withMessages(['plans.'.$i.'.occurrence_key' => 'Duplicate target/occurrence pair in this request.']);
            }
            $dedup[$identity] = true;
        }

        // One top-level transaction: any invalid approval, scope, hash, or media
        // assessment aborts all plans, including their audit records.
        return DB::transaction(function () use ($actor, $org, $brand, $plans) {
            $results = [];
            foreach ($plans as $plan) {
                $results[] = $this->intents->create($actor, $org, $brand,
                    $plan['item_uuid'], $plan['target_uuid'], (int) $plan['approval_request_id'],
                    $plan['occurrence_key'], $plan['run_at']);
            }

            return ['plans' => $results, 'count' => count($results), 'dispatch_enabled' => false];
        }, 3);
    }
}
