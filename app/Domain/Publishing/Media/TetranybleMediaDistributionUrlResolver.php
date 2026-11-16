<?php

namespace App\Domain\Publishing\Media;

use App\Models\MediaAsset;
use App\Models\Organisation;
use App\Services\Content\CanonicalStorageMediaAccess;
use Tetranyble\Storage\Modules\Storage\Application\Contracts\FileSystemContract;

/** Provider-facing short-lived URL, produced solely by Tetranyble Storage v3. */
final class TetranybleMediaDistributionUrlResolver implements MediaDistributionUrlResolverContract
{
    public function __construct(private readonly CanonicalStorageMediaAccess $canonical,
        private readonly FileSystemContract $fileSystem) {}

    public static function permitted(string $url, array $hosts): bool
    {
        $parsed = parse_url($url);
        if (! is_array($parsed) || strtolower((string) ($parsed['scheme'] ?? '')) !== 'https'
            || isset($parsed['user']) || isset($parsed['pass']) || isset($parsed['fragment'])
            || isset($parsed['port']) || strlen($url) > 4096) {
            return false;
        }
        $host = strtolower((string) ($parsed['host'] ?? ''));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return in_array($host, $hosts, true);
    }

    public function resolve(Organisation $organisation, MediaAsset $asset): string
    {
        if (! config('planner.storage_v3_adapter_verified', false)
            || ! config('planner.media_delivery_origin_verified', false)) {
            throw new \DomainException('External distribution has not been approved.');
        }
        $hosts = array_values(array_filter(array_map(
            static fn (string $host) => strtolower(trim($host)),
            explode(',', (string) config('planner.media_distribution_hosts', ''))
        )));
        if ($hosts === []) {
            throw new \DomainException('No approved media distribution origin.');
        }
        $media = $this->canonical->find($organisation, $asset);
        $url = $this->fileSystem->signedUrl($media->path, $media->disk, 60);
        if (! self::permitted($url, $hosts)) {
            throw new \DomainException('Storage URL origin not allowlisted.');
        }

        return $url;
    }
}
