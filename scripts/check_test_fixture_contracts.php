<?php

/** CI guard: no placeholder factories or no-op seeders in the membership feature. */
$root = dirname(__DIR__);
$factories = [
    'UserFactory', 'OrganisationFactory', 'BrandFactory',
    'OrganisationMembershipFactory', 'BrandMembershipFactory', 'OrganisationInvitationFactory',
];
$seeders = ['OrganisationSeeder', 'BrandSeeder', 'PlannerDevelopmentSeeder'];
$errors = [];
foreach ($factories as $name) {
    $path = "$root/database/factories/{$name}.php";
    $source = is_file($path) ? file_get_contents($path) : '';
    if (! str_contains($source, 'function definition()') || preg_match('/return\s*\[\s*\/\/\s*\];/m', $source)) {
        $errors[] = "Missing or unimplemented {$name}";
    }
}
foreach ($seeders as $name) {
    $path = "$root/database/seeders/{$name}.php";
    $source = is_file($path) ? file_get_contents($path) : '';
    if (! str_contains($source, 'function run(') || preg_match('/function run\([^)]*\).*?\{\s*\/\/\s*\}/s', $source)) {
        $errors[] = "Missing or unimplemented {$name}";
    }
}
$feature = "$root/tests/Feature/Membership/BrandMembershipEndpointsTest.php";
$source = is_file($feature) ? file_get_contents($feature) : '';
if ($source === '' || str_contains($source, 'DB::')) {
    $errors[] = 'Brand membership feature tests must use factories, not direct DB facade setup.';
}
if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors)."\n");
    exit(1);
}
fwrite(STDOUT, 'Membership factory/seeder contracts passed; feature fixtures use factories.\n');
