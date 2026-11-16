<?php

require_once __DIR__.'/../../app/Domain/Creative/CreativeImageFingerprint.php';
use App\Domain\Creative\CreativeImageFingerprint as Fingerprint;

$valid = ['sha256' => str_repeat('b', 64), 'mime_type' => 'image/png', 'byte_size' => 142223,
    'width' => 1536, 'height' => 1024];
$assertions = 0;
$check = function (bool $yes, string $label) use (&$assertions): void {
    if (! $yes) {
        throw new RuntimeException('FAILED: '.$label);
    }
    $assertions++;
};
$check(Fingerprint::matches($valid, $valid), 'exact PNG accepted');
foreach ([
    ['sha256' => str_repeat('a', 64)], ['sha256' => 'BAD'], ['sha256' => null],
    ['mime_type' => 'image/jpeg'], ['byte_size' => 0], ['byte_size' => 142222],
    ['width' => 1024], ['width' => null], ['height' => 1536], ['height' => null],
] as $changed) {
    $check(! Fingerprint::matches($valid, array_replace($valid, $changed)), 'mutated media rejected');
}
$check(! Fingerprint::matches(array_replace($valid, ['sha256' => 'garbage']), $valid), 'bad ticket hash rejected');
$check(! Fingerprint::matches(array_replace($valid, ['byte_size' => 0]), $valid), 'zero-length ticket rejected');
echo "PASS: {$assertions} creative image fingerprint assertions\n";
