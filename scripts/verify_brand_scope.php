<?php

/**
 * Dependency-free smoke check. Does not replace PHPUnit + MySQL integration.
 * Run: php scripts/verify_brand_scope.php
 */
require __DIR__.'/../app/Repository/Support/BrandScope.php';

use App\Repository\Support\BrandScope;

$checks = 0;
$scope = new BrandScope(1, 2);
if ($scope->organisationId !== 1 || $scope->brandId !== 2) {
    exit(1);
}
$checks++;
$scope->assertWritableAttributes(['name' => 'Campaign']);
$checks++;
foreach ([[0, 2], [1, 0], [-1, 2], [1, -1]] as [$org, $brand]) {
    try {
        new BrandScope($org, $brand);
        throw new RuntimeException('Invalid scope was accepted.');
    } catch (InvalidArgumentException) {
        $checks++;
    }
}
foreach (['id', 'uuid', 'organisation_id', 'brand_id'] as $key) {
    try {
        $scope->assertWritableAttributes([$key => null]);
        throw new RuntimeException('Protected attribute was accepted.');
    } catch (InvalidArgumentException) {
        $checks++;
    }
}
echo "Passed {$checks} standalone BrandScope assertions.\n";
