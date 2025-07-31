<?php

require_once __DIR__ . '/../src/Cli/Argument.php';

$args = Essentio\Cli\Argument::create();

if (!$args->get(0)) {
    throw new RuntimeException('No file specified.');
}

if (!is_dir(dirname($args->get(0)))) {
    mkdir(dirname($args->get(0)), 0777, true);
}

// Path globulator
function globBuilder(string $path): string
{
    return __DIR__ . '/../src/' . ltrim($path, '/');
}

$globs = [globBuilder('Exceptions/*.php'), globBuilder('*.php')];

if ($args->get('full') || $args->get('cli')) {
    $globs[] = globBuilder('Cli/*.php');
}

if ($args->get('full') || $args->get('api') || $args->get('http') || $args->get('web')) {
    $globs[] = globBuilder('Http/*.php');
}

if ($args->get('full') || $args->get('api')) {
    $globs[] = globBuilder('Api/*.php');
}

if ($args->get('full') || $args->get('web')) {
    $globs[] = globBuilder('Web/*.php');
}

if ($args->get('full') || ($args->get('api') || $args->get('http') || $args->get('web')) && $args->get('extra')) {
    $globs[] = globBuilder('Http/Extra/*.php');
}

if ($args->get('extra')) {
    $globs[] = globBuilder('Extra/*.php');
}

// Get files and split into classes and functions
$classes = [];
$files = [];

foreach ($globs as $glob) {
    foreach (glob($glob) as $file) {
        $file = realpath($file);

        if (!is_file($file) || !is_readable($file)) {
            continue;
        }

        if (str_ends_with($file, 'autoload.php')) {
            continue;
        }

        if (str_ends_with($file, 'functions.php')) {
            $files[] = $file;
        } else {
            $classes[] = $file;
        }
    }
}

// Compile all the things
function parseFile(string $filePath): string
{
    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    $filtered = array_filter($lines, fn($line) => !preg_match("/^\s*(namespace|use|declare\()\b/", $line));
    array_shift($filtered);
    return preg_replace('/\/\*\*\n\s\*\s@api\n\s\*\//', "", implode(PHP_EOL, $filtered));
}

$output = ["<?php"];

foreach ($classes as $class) {
    $output[] = parseFile($class);
}

foreach ($files as $class) {
    $output[] = parseFile($class);
}

file_put_contents($args->get(0), preg_replace("/\n{2,}/", "\n\n", implode(PHP_EOL, $output)) . "\n");
