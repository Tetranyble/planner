<?php

namespace Tests\Unit\Creative;

use App\Domain\Creative\CreativeImageFingerprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CreativeImageFingerprintTest extends TestCase
{
    private const PNG = ['sha256' => 'aabbccddeeff00112233445566778899aabbccddeeff00112233445566778899',
        'byte_size' => 400000, 'width' => 1024, 'height' => 1536, 'mime_type' => 'image/png'];

    public function test_matching_unchanged_png_can_be_claimed(): void
    {
        self::assertTrue(CreativeImageFingerprint::matches(self::PNG, self::PNG));
    }

    #[DataProvider('badMedia')]
    public function test_altered_or_unverified_upload_cannot_be_claimed(array $media): void
    {
        self::assertFalse(CreativeImageFingerprint::matches(self::PNG, array_replace(self::PNG, $media)));
    }

    public static function badMedia(): iterable
    {
        yield 'different hash' => [['sha256' => str_repeat('b', 64)]];
        yield 'invalid hash' => [['sha256' => 'zzz']];
        yield 'missing hash' => [['sha256' => null]];
        yield 'recompressed bytes' => [['byte_size' => 399999]];
        yield 'wrong width' => [['width' => 1536]];
        yield 'wrong height' => [['height' => 1024]];
        yield 'spoofed mime' => [['mime_type' => 'image/jpeg']];
        yield 'missing dimension' => [['width' => null]];
        yield 'empty file' => [['byte_size' => 0]];
    }
}
