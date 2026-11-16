# Pensuh.com privacy and legal update workstream

**Status:** OPEN — implementation/documentation task; **NOT published** on pensuh.com by this Planner change.

**Reason:** Pensuh marketing is one brand using Planner. A visitor to pensuh.com may have separate expectations from the administrators connecting Meta/LinkedIn/TikTok accounts to Planner. The two products must not use one ambiguous privacy notice or imply that all visitors have connected social accounts.

## Required website inventory and changes

| Public resource | Required additions or verification | Acceptance gate |
|---|---|---|
| `/privacy` (or canonical Privacy Policy path) | Identify correct controller(s) and processors; describe demo lead records, marketing attribution, retention, contact details and third-party data integrations **actually enabled**. Distinguish Pensuh website records from Planner social-account data; lawful basis, transfers and rights. | Privacy/legal review, live link, effective date, version history |
| `/cookie-policy` | Essential vs optional cookies; consent controls, analytics and marketing tags; reject works and persists, preference withdrawal. | Browser checks proving optional scripts do not run before consent |
| `/terms` | Confirm product/operator identity and describe scope accurately; no fictitious warranties or client claims. | Approved legal copy; footer link resolves |
| `/data-deletion` (proposed) | Request instructions with identity-verification channel; describe scope, deletion timing/limitations, and how to check request status. Do **not** expose provider actor IDs. | Real contact channel, accessibility check, QA |
| `/contact` and `/demo` | Privacy notice at collection, optional marketing consent separate from demo processing; no prechecked opt-in. | Form/API consent and unsubscribe test |
| `/refund-policy` and `/security` | Audit consistency with actual payments, retention, subprocessors and security controls; avoid absolute claims. | Factual product and legal review |
| Footer/nav + sitemap/robots | Add Privacy, Cookies, Terms and Data Deletion links; private deletion status URLs must be `noindex` and uncached. | Link checker + search-index audit |

## Cross-application responsibilities

1. **Planner** owns the social provider connection, data-deletion callback, request status, grant ownership, and privacy officer audit trail. Its public callback URL must be on a properly configured HTTPS Planner host; do not put it on pensuh.com unless that domain actually serves the secure endpoint.
2. **Pensuh** owns its own website privacy notice, lead-form consent records, optional analytics scripts and the first-party deletion contact flow. Only describe Planner as a processor or subprocessor if that relationship is actually established.
3. The Meta Dashboard may require a public policy URL and either callback/instructions URL. Register verified public URLs, not a local/staging domain. Validate all callbacks against the current Meta console configuration.
4. If one legal entity operates both, establish its registered name and contact in the final notices. Do not invent registration, DPO, address, retention durations, legal bases, or support mailbox.
5. Version and sign off legal notices before release. Record `published_at`, reviewer and content checksum in the compliance release log.

## Suggested website tasks (separate Pensuh Next.js repository)

- [ ] Inspect actual Pensuh marketing source and current live `/privacy`, `/cookie-policy`, `/terms`, `/security`, `/refund-policy`, `/demo` pages.
- [ ] Determine controller/processor roles for Pensuh and Planner; review applicable Nigeria Data Protection Act requirements.
- [ ] Draft privacy notice explaining web analytics, demo forms, consent, retention, external providers and rights handling.
- [ ] Publish specific data-deletion instructions and correctly link to the public Planner status checker **only** if the user already holds a confirmation code.
- [ ] Update cookie banner and privacy preferences; test rejection across reload and session boundaries.
- [ ] Audit forms and email automations for documented marketing consent and unsubscribe mechanisms.
- [ ] Update navigation/footer, metadata, sitemap, canonical URLs, and `noindex` on confirmation-status links.
- [ ] Obtain legal/privacy approval, test links + accessibility, record publication timestamp and evidence.

**Do not mark this workstream done until the live Pensuh site is updated and independently checked.**
