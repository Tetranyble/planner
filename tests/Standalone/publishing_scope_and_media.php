<?php

function config(string $name, mixed $default = null): mixed
{
    return ['social_integrations.publishing_entitlements.facebook' => true,
        'social_integrations.publishing_entitlements.linkedin' => true,
        'social_integrations.publishing_entitlements.x' => true][$name] ?? $default;
}
require __DIR__.'/../../app/Domain/Publishing/LiveContracts/PublishingScopePolicy.php';
require __DIR__.'/../../app/Domain/Publishing/LiveContracts/PreparedProviderRequest.php';
require __DIR__.'/../../app/Domain/Publishing/LiveContracts/TextPostRequestCompiler.php';
require __DIR__.'/../../app/Domain/Content/PlatformVariantRules.php';
require __DIR__.'/../../app/Domain/Content/PlatformMediaPreflightRules.php';
use App\Domain\Content\PlatformMediaPreflightRules as M;
use App\Domain\Publishing\LiveContracts\PublishingScopePolicy as S;
use App\Domain\Publishing\LiveContracts\TextPostRequestCompiler as C;

$c = 0;
$assert = function (bool $ok) use (&$c) {
    if (! $ok) {
        throw new RuntimeException('Assertion '.$c.' failed');
    }$c++;
};
$assert(S::permitted('facebook', 'PAGE', ['pages_manage_posts', 'pages_show_list', 'pages_read_engagement']));
$assert(! S::permitted('facebook', 'PAGE', ['pages_show_list']));
$assert(! S::permitted('facebook', 'PERSON', ['pages_manage_posts', 'pages_show_list', 'pages_read_engagement']));
$assert(S::permitted('x', 'CREATOR', ['tweet.write', 'users.read']));
$assert(! S::permitted('x', 'CREATOR', ['users.read']));
$assert(S::permitted('linkedin', 'MEMBER', ['w_member_social']));
$assert(S::authorRef('linkedin', 'MEMBER', 'A12_b3') === 'urn:li:person:A12_b3');
$assert(C::compile('linkedin', 'urn:li:person:A12_b3', 'Test')->receiptLocation === 'header.x-restli-id');
foreach (['instagram', 'tiktok', 'youtube'] as $provider) {
    $assert(in_array('MEDIA_REQUIRED', M::inspect($provider, [])['issues'], true));
}
$assert(M::inspect('facebook', [])['editorially_valid']);
$assert(M::inspect('instagram', [['mime_type' => 'image/jpeg', 'byte_size' => 1000, 'width' => 100, 'height' => 100]])['editorially_valid']);
$assert(in_array('INSTAGRAM_IMAGE_JPEG_REQUIRED', M::inspect('instagram', [['mime_type' => 'image/webp', 'byte_size' => 1000, 'width' => 100, 'height' => 100]])['issues'], true));
$assert(in_array('VIDEO_DURATION_MISSING', M::inspect('youtube', [['mime_type' => 'video/mp4', 'byte_size' => 1000]])['issues'], true));
echo "PASS {$c} scope/media assertions (offline)\n";
