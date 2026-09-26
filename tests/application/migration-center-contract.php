<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$detector = file_get_contents($root . '/src/Migration/SourceDetector.php');
$wizard = file_get_contents($root . '/bin/mucho-migrate.php');
$control = file_get_contents($root . '/bin/mucho');
$workflow = file_get_contents($root . '/.github/workflows/validate.yml');

foreach ([
    'src/Migration/SourceDetector.php' => $detector,
    'bin/mucho-migrate.php' => $wizard,
    'bin/mucho' => $control,
    '.github/workflows/validate.yml' => $workflow,
] as $path => $content) {
    if ($content === false) {
        throw new RuntimeException('Unable to read ' . $path);
    }
}

foreach ([
    'Cvolton',
    'GDPS-Maker',
    'read only',
    'Unknown GDPS schema',
] as $needle) {
    if (stripos($detector, $needle) === false) {
        throw new RuntimeException('Source detector contract missing: ' . $needle);
    }
}

foreach ([
    '--confirm=MIGRATE',
    'SET SESSION TRANSACTION READ ONLY',
    'Dry-run complete',
    'password_resets_required',
] as $needle) {
    if (strpos($wizard, $needle) === false) {
        throw new RuntimeException('Migration wizard contract missing: ' . $needle);
    }
}

foreach ([
    'Database & migrations',
    'Migration Center',
    'bin/mucho-migrate.php',
] as $needle) {
    if (strpos($control, $needle) === false) {
        throw new RuntimeException('Control Center migration integration missing: ' . $needle);
    }
}

echo "migration-center-contract: OK\n";
