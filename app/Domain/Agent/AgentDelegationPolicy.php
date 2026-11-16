<?php

namespace App\Domain\Agent;

/** Pure security policy: no tool can approve content, alter claims, or bypass publishing gates. */
final class AgentDelegationPolicy
{
    public const REVIEW_REQUIRED = 'REVIEW_REQUIRED';

    public const DELEGATED_PUBLISH = 'DELEGATED_APPROVED_PUBLISH';

    public const ACTIONS = ['research.capture', 'research.autorun', 'research.cancel', 'resource.promote', 'media.link', 'content.draft', 'content.target', 'approval.request', 'publication.plan', 'publication.arm'];

    public static function permits(string $mode, string $action): bool
    {
        return in_array($action, self::ACTIONS, true)
         && ($mode === self::DELEGATED_PUBLISH || ($mode === self::REVIEW_REQUIRED && $action !== 'publication.arm'));
    }

    public static function validMode(string $mode): bool
    {
        return in_array($mode, [self::REVIEW_REQUIRED, self::DELEGATED_PUBLISH], true);
    }

    public static function canonicalHash(string $action, array $payload): string
    {
        // Input is normalized and ordered recursively before hashing so a retry can't change intent.
        $normalize = function (mixed $value) use (&$normalize): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as $key => $inner) {
                $value[$key] = $normalize($inner);
            }

            return $value;
        };

        return hash('sha256', json_encode(['action' => $action, 'payload' => $normalize($payload)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public static function sources(array $sources): array
    {
        if (count($sources) > 12) {
            throw new \InvalidArgumentException('At most twelve research sources are accepted.');
        }
        $result = [];
        foreach ($sources as $s) {
            if (! is_array($s)) {
                throw new \InvalidArgumentException('Invalid research source.');
            }
            $url = (string) ($s['url'] ?? '');
            $urlParts = parse_url($url);
            if (! $urlParts || strtolower($urlParts['scheme'] ?? '') !== 'https' || empty($urlParts['host'])
               || isset($urlParts['user']) || isset($urlParts['pass']) || strlen($url) > 1500) {
                throw new \InvalidArgumentException('Research references must be ordinary HTTPS URLs without credentials.');
            }
            $result[] = ['url' => $url, 'title' => substr((string) ($s['title'] ?? ''), 0, 180),
                'note' => substr((string) ($s['note'] ?? ''), 0, 500), 'verified' => false];
        }

        return $result;
    }
}
