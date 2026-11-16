<?php

namespace Tests\Unit\Publishing;

use App\Domain\Publishing\Media\TetranybleMediaDistributionUrlResolver;
use PHPUnit\Framework\TestCase;

final class TetranybleDistributionOriginTest extends TestCase
{
    public function test_exact_origin_approval_and_private_host_denial(): void
    {
        $allowed = ['cdn.example.com'];
        self::assertTrue(TetranybleMediaDistributionUrlResolver::permitted('https://cdn.example.com/canonical/123?signature=abc', $allowed));
        foreach (['http://cdn.example.com/a', 'https://cdn.example.com.evil.test/a',
            'https://user:pass@cdn.example.com/a', 'https://127.0.0.1/a',
            'https://cdn.example.com:443/a', 'https://cdn.example.com./a',
            'https://cdn.example.com/a#fragment'] as $url) {
            self::assertFalse(TetranybleMediaDistributionUrlResolver::permitted($url, $allowed), $url);
        }
    }
}
