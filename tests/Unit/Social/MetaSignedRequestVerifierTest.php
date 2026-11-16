<?php

namespace Tests\Unit\Social;

use App\Services\Social\MetaSignedRequestVerifier;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\TestCase;

final class MetaSignedRequestVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('social_integrations.providers.facebook.client_secret', 'fixture-meta-secret');
    }

    private function sign(array $payload): string
    {
        $encoded = rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $encoded, 'fixture-meta-secret', true)), '+/', '-_'), '=');

        return $sig.'.'.$encoded;
    }

    private function payload(): array
    {
        return ['algorithm' => 'HMAC-SHA256', 'user_id' => '123456789', 'issued_at' => time()];
    }

    public function test_accepts_valid_meta_hmac_and_scoped_user_identifier(): void
    {
        $this->assertSame('123456789', app(MetaSignedRequestVerifier::class)->verify($this->sign($this->payload()))['user_id']);
    }

    public function test_rejects_tampered_signature(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        app(MetaSignedRequestVerifier::class)->verify('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA.'.explode('.', $this->sign($this->payload()))[1]);
    }

    public function test_rejects_unsupported_algorithm_even_with_valid_signature(): void
    {
        $this->expectException(BadRequestHttpException::class);
        app(MetaSignedRequestVerifier::class)->verify($this->sign([...$this->payload(), 'algorithm' => 'none']));
    }

    public function test_rejects_stale_signed_requests(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        app(MetaSignedRequestVerifier::class)->verify($this->sign([...$this->payload(), 'issued_at' => time() - 900000]));
    }

    public function test_rejects_expired_payload(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        app(MetaSignedRequestVerifier::class)->verify($this->sign([...$this->payload(), 'expires' => time() - 2]));
    }

    public function test_rejects_malformed_payload(): void
    {
        $this->expectException(BadRequestHttpException::class);
        app(MetaSignedRequestVerifier::class)->verify('invalid');
    }
}
