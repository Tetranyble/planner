<?php

/**
 * Dependency-free guard for Kinship discovery, role grants, and route middleware.
 * Complements (does not replace) the Laravel Kinship catalog/unit/integration tests.
 * Run: php scripts/check_kinship_catalog.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root.'/config/kinship.php';
$catalog = $config['catalog'];
$errors = [];
$check = static function (bool $okay, string $message) use (&$errors): void {
    if (! $okay) {
        $errors[] = $message;
    }
};
$check(($catalog['enabled'] ?? false) === true, 'Catalog must be explicitly enabled');
$check(($catalog['discovery']['enabled'] ?? false) === true, 'Model discovery must be enabled');
$separator = $catalog['separator'];
$check($separator === '.', 'Expected the configured dot separator');

$names = [];
foreach (glob($root.'/app/Models/*.php') as $modelFile) {
    $modelName = basename($modelFile, '.php');
    $resource = strtolower(preg_replace('/(?<!^)(?=[A-Z])/', '_', $modelName));
    foreach ($catalog['abilities'] as $action => $_) {
        $names[$resource.$separator.$action] = true;
    }
}
foreach ($catalog['resources'] as $resource => $meta) {
    $actions = $meta['abilities'] ?? array_keys($catalog['abilities']);
    foreach ($actions as $action) {
        $check(isset($catalog['abilities'][$action]), "Unknown ability {$resource}.{$action}");
        $names[$resource.$separator.$action] = true;
    }
}
foreach ($catalog['permissions'] as $name => $_) {
    $check(! isset($names[$name]), "Special permission duplicates discovered CRUD: {$name}");
    $names[$name] = true;
}
$roleNames = array_keys($catalog['roles']);
$check($roleNames === ['viewer', 'contributor', 'manager', 'owner'], 'Unexpected built-in role names/order');
foreach ($catalog['roles'] as $role => $definition) {
    $check(! empty($definition['permissions']), "Empty grants: {$role}");
    foreach ($definition['permissions'] as $pattern) {
        $matched = array_filter(array_keys($names), static fn (string $name): bool => $pattern === '*' || fnmatch($pattern, $name));
        $check($matched !== [], "Undefined permission pattern on {$role}: {$pattern}");
    }
}
foreach (['viewer', 'contributor', 'manager'] as $role) {
    $grants = $catalog['roles'][$role]['permissions'];
    foreach (['*', 'organisation_membership.update', 'integration.manage', 'provider_connection.view'] as $forbidden) {
        $check(! in_array($forbidden, $grants, true), "Unsafe {$role} grant: {$forbidden}");
    }
}
foreach (['routes/web.php', 'routes/api.php'] as $routeFile) {
    $routeSource = file_get_contents($root.'/'.$routeFile);
    preg_match_all('/kinship\\.permission:([a-z0-9_.-]+)/', $routeSource, $matches);
    foreach ($matches[1] as $name) {
        $check(isset($names[$name]), "Undefined route permission {$name} in {$routeFile}");
    }
    $check(! str_contains($routeSource, 'permission.authorize:'), "Legacy middleware in {$routeFile}");
}
if ($errors !== []) {
    fwrite(STDERR, "Kinship catalog guard FAILED:\n - ".implode("\n - ", $errors)."\n");
    exit(1);
}
printf(
    "Kinship catalog guard PASS: %d model files, %d discovered+special permissions, %d roles, %d route files\n",
    count(glob($root.'/app/Models/*.php')), count($names), count($roleNames), 2,
);
