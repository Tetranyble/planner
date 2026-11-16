<?php

namespace App\Domain\Publishing\YouTube;

use App\Models\MediaAsset;
use App\Models\Organisation;
use App\Services\Content\CanonicalStorageMediaAccess;
use Tetranyble\Storage\Modules\Storage\Application\Contracts\FileSystemContract;

/** Uses only the v3 package's FileSystemContract::readStream, never Laravel Storage paths. */
final class TetranybleCanonicalVideoChunkSource implements CanonicalVideoChunkSourceContract
{
    public function __construct(private readonly CanonicalStorageMediaAccess $canonical,
        private readonly FileSystemContract $fileSystem) {}

    public function read(Organisation $organisation, MediaAsset $asset, int $offset, int $length): string
    {
        if (! config('planner.storage_v3_adapter_verified', false)) {
            throw new \DomainException('Storage adapter is not released.');
        }
        if ($offset < 0 || $length <= 0 || $length > 8 * 1024 * 1024 || $offset > (int) $asset->byte_size
            || $length > (int) $asset->byte_size - $offset) {
            throw new \DomainException('Invalid media byte range.');
        }
        $media = $this->canonical->find($organisation, $asset);
        $stream = $this->fileSystem->readStream($media->path, $media->disk);
        if (! is_resource($stream)) {
            throw new \DomainException('Storage stream is unavailable.');
        }
        try {
            // Do not fall back to reading an entire remote object to simulate seeking.
            if (fseek($stream, $offset, SEEK_SET) !== 0) {
                throw new \DomainException('Storage stream is not seekable.');
            }
            $bytes = '';
            while (strlen($bytes) < $length && ! feof($stream)) {
                $part = fread($stream, min(1024 * 1024, $length - strlen($bytes)));
                if ($part === false || $part === '') {
                    break;
                }
                $bytes .= $part;
            }
            if (strlen($bytes) !== $length) {
                throw new \DomainException('Storage byte range was truncated.');
            }

            return $bytes;
        } finally {
            fclose($stream);
        }
    }
}
