<?php

namespace Tests\Unit\Publishing;

use App\Domain\Publishing\LiveContracts\PublishingScopePolicy;
use App\Domain\Publishing\LiveContracts\TextPostRequestCompiler;
use Tests\TestCase;

final class PublishingScopePolicyTest extends TestCase
{
    public function test_provider_entitlements_are_explicitly_opt_in(): void
    {
        config()->set('social_integrations.publishing_entitlements.facebook', false);
        $this->assertFalse(PublishingScopePolicy::permitted('facebook', 'PAGE',
            ['pages_show_list', 'pages_manage_posts', 'pages_read_engagement']));
        config()->set('social_integrations.publishing_entitlements.facebook', true);
        $this->assertFalse(PublishingScopePolicy::permitted('facebook', 'PAGE', ['pages_show_list']));
        $this->assertTrue(PublishingScopePolicy::permitted('facebook', 'PAGE',
            ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts']));
        $this->assertFalse(PublishingScopePolicy::permitted('facebook', 'PERSON',
            ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts']));
    }

    public function test_linkedin_author_requires_exact_supported_kind_and_identifier(): void
    {
        $this->assertSame('urn:li:person:Abc_123', PublishingScopePolicy::authorRef('linkedin', 'MEMBER', 'Abc_123'));
        $this->assertSame('urn:li:person:Abc_123',
            TextPostRequestCompiler::compile('linkedin', 'urn:li:person:Abc_123', 'Hello')->body['author']);
        $this->expectException(\DomainException::class);
        PublishingScopePolicy::authorRef('linkedin', 'ORGANIZATION', '123456');
    }
}
