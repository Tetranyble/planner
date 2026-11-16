<?php

namespace App\Domain\Social;

/** One validation contract shared by HTTP, queued tasks, MCP/UTCP, and services. */
final class GrantReconciliationRules
{
    /** @return array<string,list<string>> */
    public static function evidence(): array
    {
        return [
            'source_type' => ['required', 'in:PROVIDER_SUPPORT,PROVIDER_CONSOLE,PROVIDER_RECEIPT,INVESTIGATION'],
            'reference' => ['required', 'string', 'min:8', 'max:512', 'not_regex:/(?:access_token|refresh_token|app_secret|client_secret|bearer\s+)/i'],
            'summary' => ['required', 'string', 'min:30', 'max:4000'],
        ];
    }

    /** @return array<string,list<string>> */
    public static function decision(): array
    {
        return [
            'evidence_uuid' => ['required', 'uuid'],
            'outcome' => ['required', 'in:ATTESTED_REVOKED,CONFIRMED_ACTIVE,ESCALATED'],
            'rationale' => ['required', 'string', 'min:30', 'max:4000'],
        ];
    }
}
