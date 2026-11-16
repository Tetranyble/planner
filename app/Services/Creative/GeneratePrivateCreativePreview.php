<?php

namespace App\Services\Creative;

use App\Domain\Ai\ProviderTokenEvidence;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Generates one PRIVATE image to return to the requesting browser. This does not
 * create a canonical Storage media object, Content Studio asset, or publication.
 * Never persist the provider's base64 payload or expose raw provider errors.
 */
final class GeneratePrivateCreativePreview
{
    private const SIZES = ['1024x1024', '1536x1024', '1024x1536'];

    /** @return array{bytes:string,sha256:string,model:string,provider_usage:array,width:int,height:int} */
    public function generate(string $prompt, string $size): array
    {
        if (! in_array($size, self::SIZES, true) || mb_strlen($prompt) < 20 || mb_strlen($prompt) > 1400) {
            throw ValidationException::withMessages(['creative' => 'Invalid image size or prompt length.']);
        }

        $key = (string) config('services.planner_ai.key');
        if ($key === '') {
            throw ValidationException::withMessages(['creative' => 'The creative image provider is not configured.']);
        }

        $model = (string) config('services.planner_ai.image_model', 'gpt-image-2.5-flare');
        // Fixed provider host, one output, no automatic retry after ambiguous billing.
        $response = Http::withToken($key)->acceptJson()->asJson()->withoutRedirecting()
            ->timeout(110)->connectTimeout(5)
            ->post('https://api.openai.com/v1/images/generations', [
                'model' => $model,
                'n' => 1,
                'size' => $size,
                'quality' => 'medium',
                'output_format' => 'png',
                'prompt' => 'Create one original marketing visual for an unpublished, human-reviewed draft. '
                    .'Do not invent seals of approval, product guarantees, testimonials, prices, or statistics. '
                    .'Do not include trademarked logos unless owned by the requesting brand. Creative brief: '.$prompt,
            ]);

        if (! $response->successful() || strlen($response->body()) > 25 * 1024 * 1024) {
            throw ValidationException::withMessages(['creative' => 'Creative provider returned no usable private image.']);
        }

        return self::parseImageResponse($response->json(), $model, $size);
    }

    /** Validate every chunk CRC and require a final IEND with no trailing bytes. */
    private static function isCompletePng(string $bytes): bool
    {
        $length = strlen($bytes);
        $offset = 8;
        $hasImage = false;
        while ($offset + 12 <= $length) {
            $size = unpack('N', substr($bytes, $offset, 4))[1];
            if ($size > $length - $offset - 12) {
                return false;
            }
            $type = substr($bytes, $offset + 4, 4);
            if (! preg_match('/\A[A-Za-z]{4}\z/D', $type)) {
                return false;
            }
            $chunk = substr($bytes, $offset + 8, $size);
            $actual = crc32($type.$chunk) & 0xFFFFFFFF;
            $expected = unpack('N', substr($bytes, $offset + 8 + $size, 4))[1];
            if ($actual !== $expected) {
                return false;
            }
            $offset += $size + 12;
            if ($type === 'IDAT') {
                $hasImage = true;
            }
            if ($type === 'IEND') {
                return $size === 0 && $hasImage && $offset === $length;
            }
        }

        return false;
    }

    /** @return array{bytes:string,sha256:string,model:string,provider_usage:array,width:int,height:int} */
    public static function parseImageResponse(mixed $body, string $model, string $size): array
    {
        $encoded = is_array($body) ? ($body['data'][0]['b64_json'] ?? null) : null;
        if (! is_string($encoded) || $encoded === '' || strlen($encoded) > 24 * 1024 * 1024) {
            throw new \UnexpectedValueException('Image response was not bounded PNG base64.');
        }
        $bytes = base64_decode($encoded, true);
        if (! is_string($bytes) || strlen($bytes) < 45 || strlen($bytes) > 16 * 1024 * 1024
            || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
            || substr($bytes, 12, 4) !== 'IHDR'
            || unpack('N', substr($bytes, 8, 4))[1] !== 13
            || ! self::isCompletePng($bytes)) {
            throw new \UnexpectedValueException('Image response is not an acceptable PNG.');
        }

        $dimensions = @getimagesizefromstring($bytes);
        [$width, $height] = array_map('intval', explode('x', $size));
        if (! is_array($dimensions) || ($dimensions['mime'] ?? '') !== 'image/png'
            || (int) $dimensions[0] !== $width || (int) $dimensions[1] !== $height) {
            throw new \UnexpectedValueException('Image provider returned unexpected dimensions or format.');
        }

        return [
            'bytes' => $bytes, 'sha256' => hash('sha256', $bytes), 'model' => $model,
            'provider_usage' => ProviderTokenEvidence::fromResponse($body),
            'width' => $width, 'height' => $height,
        ];
    }
}
