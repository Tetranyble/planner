<?php

namespace App\Domain\Publishing\LiveContracts;

/** Strict host/path pinning; never trust endpoints sourced from user or provider replies. */
final class ProviderTextEgressPolicy
{
    private const HOSTS = ['facebook' => 'graph.facebook.com', 'linkedin' => 'api.linkedin.com', 'x' => 'api.x.com'];

    public static function assertAllowed(PreparedProviderRequest $request): void
    {
        $provider = strtolower($request->provider);
        $parts = parse_url($request->url);
        if (! isset(self::HOSTS[$provider]) || ! is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') !== self::HOSTS[$provider]
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \DomainException('Provider endpoint not authorized');
        }
        $path = $parts['path'] ?? '';
        $valid = match ($provider) {
            'facebook' => (bool) preg_match('~^/v[0-9]+\.[0-9]+/[0-9]{3,30}/feed$~D', $path),
            'linkedin' => $path === '/rest/posts',
            'x' => $path === '/2/tweets',
            default => false,
        };
        if (! $valid || $request->method !== 'POST') {
            throw new \DomainException('Unrecognized provider endpoint');
        }
        foreach ($request->headers as $key => $value) {
            if (! is_string($key) || ! is_string($value) || preg_match('/[\r\n]/', $key.$value)
                || in_array(strtolower($key), ['authorization', 'cookie', 'host', 'proxy-authorization'], true)) {
                throw new \DomainException('Unsafe provider header');
            }
        }
    }
}
