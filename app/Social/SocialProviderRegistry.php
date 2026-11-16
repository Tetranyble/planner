<?php

namespace App\Social;

use App\Social\Contracts\SocialOAuthProvider;
use App\Social\Providers\FacebookOAuthProvider;
use App\Social\Providers\InstagramOAuthProvider;
use App\Social\Providers\LinkedInOAuthProvider;
use App\Social\Providers\TikTokOAuthProvider;
use App\Social\Providers\XOAuthProvider;
use App\Social\Providers\YouTubeOAuthProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** Whitelisted provider registry: no runtime class names or caller-defined hosts. */
final class SocialProviderRegistry
{
    public const PROVIDERS = ['facebook', 'instagram', 'linkedin', 'x', 'tiktok', 'youtube'];

    public function __construct(
        private readonly FacebookOAuthProvider $facebook,
        private readonly InstagramOAuthProvider $instagram,
        private readonly LinkedInOAuthProvider $linkedin,
        private readonly XOAuthProvider $x,
        private readonly TikTokOAuthProvider $tiktok,
        private readonly YouTubeOAuthProvider $youtube,
    ) {}

    public function resolve(string $provider): SocialOAuthProvider
    {
        $adapter = match ($provider) {
            'facebook' => $this->facebook,
            'instagram' => $this->instagram,
            'linkedin' => $this->linkedin,
            'x' => $this->x,
            'tiktok' => $this->tiktok,
            'youtube' => $this->youtube,
            default => throw new NotFoundHttpException('Unknown social provider.'),
        };
        if (! $this->isConfigured($provider)) {
            throw new ServiceUnavailableHttpException(null, 'Social provider is not configured.');
        }

        return $adapter;
    }

    public function isConfigured(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true)
            && filled(config("social_integrations.providers.{$provider}.client_id"))
            && filled(config("social_integrations.providers.{$provider}.client_secret"));
    }

    public function availability(): array
    {
        return collect(self::PROVIDERS)->mapWithKeys(fn (string $provider): array => [
            $provider => $this->isConfigured($provider),
        ])->all();
    }
}
