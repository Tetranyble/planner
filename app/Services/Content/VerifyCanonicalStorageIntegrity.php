<?php

namespace App\Services\Content;

use App\Domain\Publishing\YouTube\CanonicalVideoChunkSourceContract;
use App\Models\MediaAsset;
use App\Models\Organisation;

/** Reads bounded canonical byte ranges through the reviewed package adapter and hashes end-to-end. */
final class VerifyCanonicalStorageIntegrity
{
    public function __construct(private readonly CanonicalVideoChunkSourceContract $source) {}

    public function check(Organisation $org, MediaAsset $asset, int $limitBytes): array
    {
        $total = (int) $asset->byte_size;
        if ($total < 1 || $total > $limitBytes || $total > 1024 * 1024 * 1024) {
            return ['passed' => false, 'code' => 'MEDIA_SIZE_NOT_WITHIN_VERIFICATION_LIMIT'];
        }
        if (! preg_match('/^[a-f0-9]{64}$/D', strtolower((string) $asset->sha256))) {
            return ['passed' => false, 'code' => 'INVALID_CANONICAL_SHA256'];
        }
        $digest = hash_init('sha256');
        $offset = 0;
        try {
            while ($offset < $total) {
                $bytes = $this->source->read($org, $asset, $offset, min(4 * 1024 * 1024, $total - $offset));
                if ($bytes === '') {
                    return ['passed' => false, 'code' => 'STORAGE_RANGE_UNAVAILABLE'];
                }
                hash_update($digest, $bytes);
                $offset += strlen($bytes);
            }
        } catch (\Throwable) {
            return ['passed' => false, 'code' => 'STORAGE_READ_FAILED'];
        }
        $actual = hash_final($digest);

        return ['passed' => hash_equals(strtolower((string) $asset->sha256), $actual),
            'code' => hash_equals(strtolower((string) $asset->sha256), $actual) ? 'CANONICAL_HASH_CONFIRMED' : 'CANONICAL_HASH_MISMATCH',
            'verified_bytes' => $offset];
    }
}
