# Increment 47 — Social engagement evidence import and reporting

**Batch 4 remains open.** Source implementation added manually entered provider-export snapshots, not API-verified engagement.

## Delivered

- Additive MySQL `social_metric_snapshots` migration with scoped composite brand FK, idempotency key, provenance, stable payload hash, immutable individual observations and index for tenant reporting.
- `SocialMetricRepositoryContract` and Eloquent implementation: scoped reads, insert-only writes, latest observation per account/post in date range, 500-row cap with truncated totals withheld.
- `ManageSocialMetricSnapshots`: independent service-level Kinship authorization, locked organisation and brand, same-key same-payload idempotency, audit record and UTC observation range checks.
- Scoped `GET/POST .../content/social-metrics` routes, invokable-style? No: resource controller `index/store` with Kinship edge checks, role guard, throttling and validation.
- React Content Studio evidence pane with date filter, source labels, manual entry form and explicit unverified metric semantics.
- Factory, MySQL integration test and static architecture tests.

## Invariants and limitations

1. **No remote API integrations.** `USER_REPORTED_UNVERIFIED` is always returned; counts are not authoritative.
2. Older measurements for the same provider/account/post are not added to the latest figure, preventing obvious cumulative double-counting.
3. Totals are null if the 500-snapshot limit is exceeded. Narrow the reporting window to avoid partial results.
4. Accepted source types: manual export and screenshot reference only; this app does NOT ingest or verify screenshot bytes or remote exports. Evidence references are pointers, not cryptographic provenance verification.
5. Never change or delete metric evidence through the API. No automatic posting or provider access is enabled.
6. Run `php artisan migrate`, `php artisan test --testsuite=Integration --filter=SocialMetricPersistenceTest`, `node --test tests/Frontend/social-metrics-architecture.test.mjs` on your configured MySQL environment. No MySQL/PHPUnit execution was possible here.

## Remaining work toward Batch 4

Provider API metric ingestion, signed/evidenced provenance, background sync, rate limits, comparative campaign attribution, verified spend/conversion metrics, automation rules and monitoring. Pensuh.com privacy/legal pages remain not deployed.
