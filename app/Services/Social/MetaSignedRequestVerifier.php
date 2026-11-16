<?php

namespace App\Services\Social;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** Verify Meta's base64url(payload).HMAC-SHA256 signed_request without logging it. */
final class MetaSignedRequestVerifier
{
    /** @return array{user_id:string,issued_at:int} */
    public function verify(string $signedRequest): array
    {
        $secret = (string) config('social_integrations.providers.facebook.client_secret', '');
        if ($secret === '') {
            throw new ServiceUnavailableHttpException(null, 'Meta deletion callback is not configured.');
        }
        if (strlen($signedRequest) > 8192 || substr_count($signedRequest, '.') !== 1) {
            throw new BadRequestHttpException('Malformed signed request.');
        }
        [$signaturePart, $payloadPart] = explode('.', $signedRequest, 2);
        $signature = $this->decode($signaturePart);
        $payload = $this->decode($payloadPart);
        if ($signature === null || $payload === null || strlen($signature) !== 32) {
            throw new BadRequestHttpException('Malformed signed request.');
        }
        if (! hash_equals(hash_hmac('sha256', $payloadPart, $secret, true), $signature)) {
            throw new AccessDeniedHttpException('Invalid signed request.');
        }
        try {
            $data = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Malformed signed request.');
        }
        if (! is_array($data) || strtoupper((string) ($data['algorithm'] ?? '')) !== 'HMAC-SHA256') {
            throw new BadRequestHttpException('Unsupported signed request algorithm.');
        }
        $id = $data['user_id'] ?? null;
        if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $id)) {
            throw new BadRequestHttpException('Invalid app-scoped user identifier.');
        }
        $issuedAt = $data['issued_at'] ?? null;
        $maxAge = (int) config('social_integrations.meta_deletion_max_age_seconds', 604800);
        if (! is_int($issuedAt) || $issuedAt > time() + 300 || $issuedAt < time() - $maxAge) {
            throw new AccessDeniedHttpException('Expired signed request.');
        }
        if (isset($data['expires']) && (! is_int($data['expires']) || $data['expires'] < time())) {
            throw new AccessDeniedHttpException('Expired signed request.');
        }

        return ['user_id' => $id, 'issued_at' => $issuedAt];
    }

    private function decode(string $part): ?string
    {
        if ($part === '' || ! preg_match('/^[A-Za-z0-9_-]+$/D', $part)) {
            return null;
        }
        $bytes = base64_decode(strtr($part, '-_', '+/').str_repeat('=', (4 - strlen($part) % 4) % 4), true);

        return $bytes === false ? null : $bytes;
    }
}
