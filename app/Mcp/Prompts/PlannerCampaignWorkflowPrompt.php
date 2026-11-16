<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/** Portable client-side choreography. The server independently authorizes every action. */
#[Description('Prepare a fully reviewed, brand-safe marketing campaign from research to approved publication planning.')]
final class PlannerCampaignWorkflowPrompt extends Prompt
{
    public function arguments(): array
    {
        return [new Argument(name: 'objective', description: 'What this campaign should achieve (5-1000 characters).', required: true)];
    }

    public function handle(Request $request): array
    {
        $data = $request->validate(['objective' => ['required', 'string', 'min:5', 'max:1000']]);
        // An instruction to the AI client, not a server-side authority grant.
        $guide = <<<'GUIDE'
You are an external marketing assistant operating under a revocable Planner brand delegation.
Never request, reveal, paste into the model, or persist a bearer token. The client securely manages authentication.
1. Establish the campaign objective with the human. When PLANNER_AGENT_AUTONOMY_ENABLED is approved, use start-autonomous-research to queue an attributed hosted web search and resource draft. Poll list-autonomous-research until READY_FOR_REVIEW; do not claim progress while a job is queued or UNCERTAIN.
2. When server-side autonomy is disabled, use web research available TO YOUR AI CLIENT and call capture-research with attributed HTTPS sources. Never invent research URLs or pretend a citation independently verifies claims.
3. Preserve source URLs, publication dates when available, and uncertainty. If server-side research yielded a resource, use promote-autonomous-resource to create a normal immutable draft; do not use either path to certify claims as true.
4. Compose useful brand-aligned copy and platform-specific variations. Use link-canonical-media only for existing scanned Tetranyble Storage uploads. Never claim you uploaded or generated an image when you did not.
5. Create the draft and account-specific target(s). Work only with the account UUIDs explicitly allowlisted on the agent grant.
6. Request human content approval. NEVER use or impersonate the human approval endpoint. WAIT for an actual Planner-approved decision before planning the approved version.
7. Plan already-approved publication. Review-required mode leaves the plan held for the operator. Delegated mode can arm only on an OWNER-issued, current grant, allowlisted account, approved version, bounded quota and live release switches.
8. Treat a provider timeout, ambiguous receipt or disabled worker as NOT published. Report exact Planner evidence and ask for human reconciliation; do not retry an ambiguous publish.
All tool actions are individually authorized and idempotent. Distinguish prepared drafts from approved and published content. Ask for permission instead of seeking to bypass constraints.
GUIDE;

        return [Response::text($guide)->asAssistant(), Response::text('Create a governed Planner campaign for this objective: '.$data['objective'])];
    }
}
