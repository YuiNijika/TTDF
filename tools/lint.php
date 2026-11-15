<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directories = [
    $root . '/vendor/yuinijika/ttdf/core',
    $root . '/app',
    $root . '/tests',
    $root . '/tools',
];
$files = [
    $root . '/functions.php',
    $root . '/index.php',
];

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

$failed = 0;
foreach (array_unique($files) as $file) {
    $source = file_get_contents($file);
    if ($source === false || preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/', $source) !== 1) {
        $failed++;
        fwrite(STDERR, sprintf("%s: missing declare(strict_types=1)\n", $file));
        continue;
    }

    $command = 'php -l ' . escapeshellarg($file);
    exec($command, $output, $status);
    if ($status !== 0) {
        $failed++;
        fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
    }
    $output = [];
}

if ($failed > 0) {
    fwrite(STDERR, sprintf("%d file(s) failed linting.\n", $failed));
    exit(1);
}

echo sprintf("Linted %d files successfully.\n", count(array_unique($files)));
