# Kinship / Storage integration status

| Area | Source patched | Verified in DB / package runtime |
|---|---:|---:|
| Kinship workspace config and permission names | Yes | Not yet |
| User/organisation workspace contracts | Yes | Not yet |
| Request-scoped fail-closed context | Yes | Not yet |
| Role grant/revocation service | Yes | Not yet |
| Brand membership and dual authorization | Yes | Not yet |
| Storage host models, tables, computed context | Yes | Not yet |
| Media asset linking column | Yes | Not yet |
| Tenant FKs, hierarchy constraints, scheduler | Yes | Not yet |
| PHP syntax + static migration dependency checks | Yes | Yes |
| End-to-end social publishing / MCP / UTCP | No | No |

Recommended blocker before production: run Composer-installed Kinship and Storage contracts against a real MySQL instance; do not expose Storage routes or external AI write tools until that gate is green.
