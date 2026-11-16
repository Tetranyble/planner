<?php

// Dependency-free sandbox isolation guard; does not replace Laravel/MySQL integration tests.
$root = dirname(__DIR__);
$read = fn ($f) => file_get_contents($root.'/'.$f);
$service = $read('app/Services/Publishing/RunPublicationSandbox.php');
$repo = $read('app/Repository/Eloquent/PublicationSandboxRepository.php');
$migration = $read('database/migrations/2026_11_14_210000_create_publication_sandbox_runs.php');
$checks = [
    'separate sandbox table' => str_contains($migration, "Schema::create('publication_sandbox_runs'"),
    'immutable occurrence operation key' => str_contains($migration, 'uq_sandbox_operation'),
    'parent tenant foreign key' => str_contains($migration, 'fk_sandbox_parent'),
    'only held jobs accepted' => str_contains($service, '$'."job->state!=='AWAITING_USER'"),
    'simulation harness only' => str_contains($service, 'SimulationHarness::run('),
    'stale lease quarantined' => str_contains($service, "'STALE_LEASE_QUARANTINED'"),
    'pessimistic row locks' => str_contains($repo, 'for update skip locked'),
    'no provider transport in service' => ! str_contains($service, 'Http::') && ! str_contains($service, 'curl_') && ! str_contains($service, 'ProviderDispatchContract'),
    'no live job mutations' => ! str_contains($service, 'PublicationJob::') && ! str_contains($service, 'job->save('),
];
foreach ($checks as $key => $ok) {
    echo ($ok ? 'PASS' : 'FAIL').': '.$key.PHP_EOL;
    if (! $ok) {
        exit(1);
    }
}echo count($checks).' sandbox guards passed'.PHP_EOL;
