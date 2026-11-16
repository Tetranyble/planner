<?php

require_once __DIR__.'/../../app/Domain/Ai/ProviderTokenEvidence.php';
require_once __DIR__.'/../../app/Services/Creative/GeneratePrivateCreativePreview.php';

use App\Services\Creative\GeneratePrivateCreativePreview;

$assertions = 0;
$assert = static function (bool $condition, string $why) use (&$assertions): void {
    if (! $condition) {
        throw new RuntimeException('FAILED: '.$why);
    }
    $assertions++;
};

$chunk = static function (string $name, string $payload): string {
    return pack('N', strlen($payload)).$name.$payload.pack('N', crc32($name.$payload));
};
$bytes = "\x89PNG\r\n\x1a\n"
    .$chunk('IHDR', pack('NNCCCCC', 1024, 1024, 8, 2, 0, 0, 0))
    .$chunk('IDAT', gzcompress(str_repeat("\0".str_repeat("\0", 1024 * 3), 1024)))
    .$chunk('IEND', '');
$body = ['data' => [['b64_json' => base64_encode($bytes)]], 'usage' => ['input_tokens' => 11, 'output_tokens' => 21]];
$result = GeneratePrivateCreativePreview::parseImageResponse($body, 'image-fixture', '1024x1024');
$assert($result['bytes'] === $bytes, 'unchanged generated image bytes');
$assert($result['sha256'] === hash('sha256', $bytes), 'image digest');
$assert($result['width'] === 1024 && $result['height'] === 1024, 'requested pixel dimensions');
$assert($result['provider_usage']['input_tokens'] === 11, 'provider usage evidence');
foreach ([
    ['data' => []],
    ['data' => [['b64_json' => base64_encode('<html>bogus</html>')]]],
    ['data' => [['b64_json' => base64_encode($bytes.'TRAILING_BYTES')]]],
    ['data' => [['b64_json' => base64_encode(substr_replace($bytes, "\x01", 28, 1))]]],
] as $index => $invalid) {
    try {
        GeneratePrivateCreativePreview::parseImageResponse($invalid, 'image-fixture', '1024x1024');
        throw new RuntimeException('Failed to reject invalid fixture '.$index);
    } catch (UnexpectedValueException) {
        $assert(true, 'malformed image is rejected');
    }
}
try {
    GeneratePrivateCreativePreview::parseImageResponse($body, 'image-fixture', '1024x1536');
    throw new RuntimeException('Accepted unexpected image dimensions');
} catch (UnexpectedValueException) {
    $assert(true, 'wrong dimensions rejected');
}
echo "PASS: {$assertions} private creative-preview assertions\n";
