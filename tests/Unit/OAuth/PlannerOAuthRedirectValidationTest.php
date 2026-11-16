<?php

namespace Tests\Unit\OAuth;

use App\Services\OAuth\RegisterPlannerOAuthClient;
use PHPUnit\Framework\TestCase;

final class PlannerOAuthRedirectValidationTest extends TestCase
{
    public function test_only_exact_https_callback_hosts_are_allowed(): void
    {
        $hosts = ['chatgpt.com'];
        self::assertTrue(RegisterPlannerOAuthClient::permittedRedirect('https://chatgpt.com/oauth/callback', $hosts));
        foreach ([
            'http://chatgpt.com/oauth/callback',
            'https://chatgpt.com.attacker.test/oauth/callback',
            'https://chatgpt.com@evil.test/oauth/callback',
            'https://chatgpt.com:444/oauth/callback',
            'https://chatgpt.com/oauth/callback#fragment',
            'file:///tmp/token',
        ] as $url) {
            self::assertFalse(RegisterPlannerOAuthClient::permittedRedirect($url, $hosts), $url);
        }
    }
}
