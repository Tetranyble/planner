<?php

namespace App\Domain\Agent;

/** Pure, bounded evidence extraction. A citation is NOT independent fact verification. */
final class AutonomousResearchEvidence
{
    public static function fromResponse(array $response): array
    {
        $searched = false;
        $parts = [];
        $sources = [];
        foreach (($response['output'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['type'] ?? '') === 'web_search_call' && ($item['status'] ?? '') === 'completed') {
                $searched = true;
            }
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach (($item['content'] ?? []) as $part) {
                if (! is_array($part) || ($part['type'] ?? '') !== 'output_text') {
                    continue;
                }
                $parts[] = (string) ($part['text'] ?? '');
                foreach (($part['annotations'] ?? []) as $a) {
                    if (! is_array($a) || ($a['type'] ?? '') !== 'url_citation') {
                        continue;
                    }
                    $url = (string) ($a['url'] ?? '');
                    $u = parse_url($url);
                    if (! $u || strtolower((string) ($u['scheme'] ?? '')) !== 'https' || empty($u['host'])
                      || isset($u['user']) || isset($u['pass']) || strlen($url) > 1500) {
                        continue;
                    }
                    if (! isset($sources[$url])) {
                        $sources[$url] = [
                            'url' => $url, 'title' => substr((string) ($a['title'] ?? ''), 0, 180),
                            'note' => 'Provider-returned URL citation; not independently verified.', 'verified' => false];
                    }
                }
            }
        }
        $text = trim(implode("\n", $parts));
        if (! $searched || ! count($sources) || $text === '' || strlen($text) > 12000) {
            throw new \UnexpectedValueException('Research did not provide bounded text and cited web-search evidence.');
        }
        $citations = array_slice(array_values($sources), 0, 12);
        $digest = AgentDelegationPolicy::canonicalHash('research.evidence', [
            'findings' => $text, 'sources' => $citations]);

        return ['findings' => $text, 'sources' => $citations, 'research_hash' => $digest, 'sources_verified' => false];
    }

    public static function resourceHash(string $text): string
    {
        return hash('sha256', $text);
    }
}
