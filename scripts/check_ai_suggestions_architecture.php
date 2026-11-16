<?php

$root = dirname(__DIR__);
$service = file_get_contents($root.'/app/Services/Ai/ManageAiSuggestions.php');
$routes = file_get_contents($root.'/routes/api.php');
$gateway = file_get_contents($root.'/app/Services/Ai/GenerateBrandCopy.php');
foreach (['content_item.store', 'result_hash', 'ai_generated', 'studio->create', 'repo->promote', 'DB::transaction'] as $required) {
    if (! str_contains($service, $required)) {
        throw new RuntimeException("Missing safety marker: $required");
    }
}
foreach (['kinship.permission:content_item.store', 'throttle:5,1', 'ai-suggestions/{suggestionUuid}/promote'] as $required) {
    if (! str_contains($routes, $required)) {
        throw new RuntimeException("Missing route marker: $required");
    }
}
foreach (["'store'=>false", 'api.openai.com/v1/responses'] as $required) {
    if (! str_contains($gateway, $required)) {
        throw new RuntimeException("Missing gateway marker: $required");
    }
}
echo "AI suggestions static safety checks: PASS\n";
