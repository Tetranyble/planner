<?php

namespace Tests\Unit\Creative;

use App\Services\Creative\GeneratePrivateCreativePreview;
use PHPUnit\Framework\TestCase;

final class PrivateCreativeImageValidationTest extends TestCase
{
    private function png(int $width, int $height): string
    {
        $chunk = static function (string $type, string $payload): string {
            return pack('N', strlen($payload)).$type.$payload.pack('N', crc32($type.$payload));
        };
        $header = "\x89PNG\r\n\x1a\n";
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $scan = str_repeat("\0".str_repeat("\0", $width * 3), $height);

        return $header.$chunk('IHDR', $ihdr).$chunk('IDAT', gzcompress($scan)).$chunk('IEND', '');
    }

    public function test_accepts_only_requested_png_size_and_reports_digest(): void
    {
        $png = $this->png(1024, 1024);
        $result = GeneratePrivateCreativePreview::parseImageResponse([
            'data' => [['b64_json' => base64_encode($png)]],
            'usage' => ['input_tokens' => 14, 'output_tokens' => 20],
        ], 'image-fixture', '1024x1024');
        self::assertSame($png, $result['bytes']);
        self::assertSame(hash('sha256', $png), $result['sha256']);
        self::assertSame(1024, $result['width']);
        self::assertSame(14, $result['provider_usage']['input_tokens']);
    }

    public function test_rejects_html_disguised_as_png(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        GeneratePrivateCreativePreview::parseImageResponse(['data' => [[
            'b64_json' => base64_encode('<script>alert(1)</script>'),
        ]]], 'image-fixture', '1024x1024');
    }

    public function test_rejects_image_of_wrong_dimensions(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        GeneratePrivateCreativePreview::parseImageResponse(['data' => [[
            'b64_json' => base64_encode($this->png(1024, 1024)),
        ]]], 'image-fixture', '1024x1536');
    }

    public function test_rejects_missing_provider_image(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        GeneratePrivateCreativePreview::parseImageResponse(['data' => []], 'image-fixture', '1024x1024');
    }
}
