<?php

namespace App\Domain\Publishing\Delivery;

/** Pure defence-in-depth gate: configuration alone can never approve a live publish. */
final class DispatchSafetyGate
{
    public static function evaluate(array $facts): array
    {
        $checks = ['remote_kill_switch_off', 'provider_validated', 'scope_validated', 'account_active',
            'credential_healthy', 'version_approved', 'policy_current', 'media_verified',
            'operation_key_persisted', 'idempotency_supported', 'lease_owned', 'run_due'];
        $blockers = [];
        foreach ($checks as $check) {
            if (($facts[$check] ?? false) !== true) {
                $blockers[] = 'GATE_'.strtoupper($check);
            }
        }
        // There are intentionally no HTTP implementations registered, regardless of check values.
        $blockers[] = 'NO_LIVE_PROVIDER_ADAPTER_REGISTERED';

        return ['may_dispatch' => false, 'blockers' => $blockers, 'network_requests' => 0];
    }
}
