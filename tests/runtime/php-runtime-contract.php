<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$dockerfile = file_get_contents($root . "/docker/Dockerfile");
$composer = file_get_contents($root . "/composer.json");
$workflow = file_get_contents($root . "/.github/workflows/validate.yml");

foreach ([
    "docker/Dockerfile" => $dockerfile,
    "composer.json" => $composer,
    ".github/workflows/validate.yml" => $workflow,
] as $path => $content) {
    if ($content === false) {
        throw new RuntimeException("Unable to read " . $path);
    }
}

if (!preg_match("/^ARG PHP_VERSION=8\\.5$/m", $dockerfile)) {
    throw new RuntimeException("Docker runtime must default to PHP 8.5.");
}

if (strpos($dockerfile, "FROM php:${PHP_VERSION}-fpm-bookworm") === false) {
    throw new RuntimeException("Docker runtime must use the configurable PHP_VERSION argument.");
}

if (strpos($composer, '"php": ">=8.3 <9.0"') === false) {
    throw new RuntimeException("Composer must declare the supported PHP runtime window.");
}

foreach (['"8.3"', '"8.4"', '"8.5"'] as $version) {
    if (strpos($workflow, $version) === false) {
        throw new RuntimeException("PHP compatibility matrix is missing " . $version . ".");
    }
}

if (strpos($workflow, "php-runtime") === false) {
    throw new RuntimeException("Validate workflow must include the PHP runtime compatibility job.");
}

echo "php-runtime-contract: OK\n";