<?php

/**
 * Lightweight architecture guard for new repositories. Complements tests.
 * Brand-owned repositories may not derive from unrestricted BaseRepository.
 */
$paths = glob(__DIR__.'/../app/Repository/Eloquent/*.php') ?: [];
$failures = [];
foreach ($paths as $path) {
    if (basename($path) === 'UserRepository.php') {
        continue;
    }
    $contents = file_get_contents($path);
    if (preg_match('/extends\s+BaseRepository\b/', $contents)) {
        $failures[] = basename($path).' must extend BrandScopedRepository, not BaseRepository';
    }
}
if ($failures) {
    fwrite(STDERR, implode("\n", $failures)."\n");
    exit(1);
}
echo 'Repository architecture guard passed ('.count($paths)." Eloquent repositories).\n";
