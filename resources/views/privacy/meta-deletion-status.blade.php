<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Data deletion request — Planner</title>
<style>body{font-family:system-ui,sans-serif;line-height:1.65;max-width:42rem;margin:4rem auto;padding:0 1.5rem;color:#111827}section{background:#f7f8fc;border:1px solid #e6e9f1;border-radius:14px;padding:1.5rem}code{overflow-wrap:anywhere}</style></head>
<body>
<main>
<h1>Data deletion request</h1>
<section><p><strong>Confirmation:</strong> <code>{{ $code }}</code></p>
<p><strong>Status:</strong> {{ match($state) {
 'PENDING' => 'Received — awaiting processing',
 'PROCESSING' => 'Processing',
 'COMPLETED' => 'Completed',
 'COMPLETED_WITH_RETENTION' => 'Processing completed with legally retained records',
 'NEEDS_REVIEW' => 'Additional review required',
 'FAILED' => 'Processing encountered an error; retry or review required',
 default => 'Received',
} }}</p>
<p>Received: {{ $acceptedAt?->toIso8601String() }}</p>
@if($completedAt)<p>Completed: {{ $completedAt->toIso8601String() }}</p>@endif
@if($state === 'NEEDS_REVIEW' && $reason === 'PROCESSING_RETRIES_EXHAUSTED')
<p>Automated processing could not be completed after repeated errors. The request is still open and requires a privacy officer to verify and complete the remaining erasure.</p>
@elseif($state === 'NEEDS_REVIEW')
<p><strong>Why is review needed?</strong> Associated business-authored publication records or unlinked provider events may have separate ownership or retention obligations. Access tokens and mapped provider profile data have already been removed. We cannot mark the request complete until those remaining records have been reviewed and any personal data is erased.</p>
@elseif($state === 'COMPLETED_WITH_RETENTION')
<p>Provider access and eligible provider-derived data were removed. Some records were retained under a documented legal obligation, legal-claims necessity, or regulatory hold. Those records remain subject to restricted access and future review. Contact the privacy office through the published privacy policy if you wish to challenge this decision.</p>
@elseif($state === 'FAILED')
<p>A processing error prevented us from confirming erasure. The request remains open and requires retry or a manual privacy review.</p>
@elseif($state === 'PENDING' || $state === 'PROCESSING')
<p>Access credentials for matching grants were revoked when the request was accepted. We are checking for and erasing associated data.</p>
@else
<p>No outstanding provider-derived data was identified within the mapped authorization records. The request is complete.</p>
@endif
<p>This page intentionally omits the social account identity and organisation details. If you believe the status is incorrect, refer to the privacy contact listed in the application's published privacy policy.</p></section>
</main></body></html>
